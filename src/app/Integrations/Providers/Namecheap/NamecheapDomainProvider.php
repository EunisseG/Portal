<?php

namespace App\Integrations\Providers\Namecheap;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\DomainGateway;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de dominios vía la API de Namecheap.
 *
 * La API de Namecheap es XML sobre GET a un único endpoint (xml.response).
 * Cada operación es un `Command` distinto y comparte los parámetros globales
 * de autenticación:
 *   - ApiUser / UserName: usuario de la cuenta Namecheap
 *   - ApiKey: clave de API (secreto cifrado)
 *   - ClientIp: IP pública autorizada en el panel de Namecheap
 *
 * Config (integración 'domains'):
 *   - config: api_user, username, client_ip, sandbox (bool)
 *   - secret: api_key (cifrado)
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin credenciales
 * devuelve fail sin llamar a la API. La respuesta XML se parsea a array y
 * se evalúa el atributo Status="OK" / "ERROR".
 *
 * Namecheap trabaja con "SLD" (segundo nivel) + "TLD" por separado en varios
 * comandos, por eso partimos el dominio en ambos.
 */
class NamecheapDomainProvider implements DomainGateway, IntegrationProvider
{
    private const PRODUCTION = 'https://api.namecheap.com/xml.response';

    private const SANDBOX = 'https://api.sandbox.namecheap.com/xml.response';

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'domains';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Domains;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiUser())
            && ! empty($this->clientIp())
            && $this->apiKey() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Namecheap no configurado: falta api_user, client_ip o api_key.');
        }

        // Comando ligero: lista los TLD disponibles.
        return $this->command('namecheap.domains.getTldList', []);
    }

    public function register(string $domain, int $years = 1): GatewayResult
    {
        return $this->command('namecheap.domains.create', [
            'DomainName' => $domain,
            'Years' => $years,
        ]);
    }

    public function renew(string $domain, int $years = 1): GatewayResult
    {
        return $this->command('namecheap.domains.renew', [
            'DomainName' => $domain,
            'Years' => $years,
        ]);
    }

    public function transfer(string $domain, array $data = []): GatewayResult
    {
        return $this->command('namecheap.domains.transfer.create', array_merge([
            'DomainName' => $domain,
            'Years' => 1,
        ], $data));
    }

    public function getInfo(string $domain): GatewayResult
    {
        return $this->command('namecheap.domains.getInfo', [
            'DomainName' => $domain,
        ]);
    }

    /**
     * Consulta la disponibilidad de uno o varios dominios (domains.check).
     */
    public function check(string ...$domains): GatewayResult
    {
        return $this->command('namecheap.domains.check', [
            'DomainList' => implode(',', $domains),
        ]);
    }

    /**
     * Ejecuta un comando de la API de Namecheap y parsea la respuesta XML.
     *
     * @param  array<string, mixed>  $params
     */
    private function command(string $command, array $params): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Namecheap no configurado: falta api_user, client_ip o api_key.');
        }

        $query = array_merge([
            'ApiUser' => $this->apiUser(),
            'ApiKey' => $this->apiKey(),
            'UserName' => $this->username(),
            'ClientIp' => $this->clientIp(),
            'Command' => $command,
        ], $params);

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->get($this->endpoint(), $query);

            if (! $response->successful()) {
                return GatewayResult::fail('Namecheap: HTTP '.$response->status(), [
                    'http_status' => $response->status(),
                ]);
            }

            return $this->parse($command, $response->body());
        } catch (\Throwable $e) {
            Log::error('[NamecheapDomainProvider] '.$command.' failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Namecheap: '.$e->getMessage());
        }
    }

    /**
     * Convierte el XML de Namecheap en GatewayResult evaluando Status.
     */
    private function parse(string $command, string $xml): GatewayResult
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($parsed === false) {
            return GatewayResult::fail('Namecheap: respuesta XML no válida.');
        }

        $data = json_decode(json_encode($parsed), true) ?: [];
        $status = (string) ($parsed['Status'] ?? '');

        if (strtoupper($status) !== 'OK') {
            $reason = $this->extractError($parsed);

            return GatewayResult::fail('Namecheap: '.$reason, ['body' => $data]);
        }

        return GatewayResult::ok('Namecheap: '.$command.' OK', ['body' => $data]);
    }

    private function extractError(\SimpleXMLElement $parsed): string
    {
        // Los errores llegan en <Errors><Error Number="...">mensaje</Error></Errors>.
        $errors = $parsed->Errors->Error ?? null;

        if ($errors !== null && count($errors) > 0) {
            return trim((string) $errors[0]);
        }

        return 'error desconocido';
    }

    private function endpoint(): string
    {
        return $this->sandbox() ? self::SANDBOX : self::PRODUCTION;
    }

    private function sandbox(): bool
    {
        return (bool) ($this->integration->config['sandbox'] ?? false);
    }

    private function apiUser(): ?string
    {
        return $this->integration->config['api_user'] ?? null;
    }

    private function username(): string
    {
        // UserName suele coincidir con ApiUser salvo cuentas revendedoras.
        return $this->integration->config['username'] ?? ($this->apiUser() ?? '');
    }

    private function clientIp(): ?string
    {
        return $this->integration->config['client_ip'] ?? null;
    }

    private function apiKey(): ?string
    {
        return $this->integration->secrets()->where('key', 'api_key')->first()?->reveal();
    }
}
