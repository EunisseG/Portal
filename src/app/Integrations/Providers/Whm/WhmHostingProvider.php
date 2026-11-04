<?php

namespace App\Integrations\Providers\Whm;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Services\Models\Server;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\HostingGateway;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de hosting vía WHM API 1 (token-based auth).
 *
 * La conexión puede provenir de dos orígenes:
 *   - Una integración 'whm' (config: base_url, whm_user; secret: api_token).
 *   - Un Servidor concreto (Server) con su propia conexión WHM. Así se
 *     soportan múltiples servidores WHM: cada servicio opera contra el WHM
 *     del servidor donde vive.
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin base_url/token
 * devuelve fail sin llamar a la API. Autenticación por header:
 *   Authorization: whm {user}:{token}
 */
class WhmHostingProvider implements HostingGateway, IntegrationProvider
{
    private ?string $baseUrl = null;

    private string $whmUser = 'root';

    private ?string $apiToken = null;

    private bool $verifyTls = false;

    /**
     * Constructor por integración (compatibilidad con el registry/binding).
     * Si se pasa una integración, sus datos se usan como conexión.
     */
    public function __construct(protected readonly ?Integration $integration = null)
    {
        if ($integration) {
            $this->baseUrl = $integration->config['base_url'] ?? null;
            $this->whmUser = $integration->config['whm_user'] ?? 'root';
            $this->apiToken = $integration->secrets()->where('key', 'api_token')->first()?->reveal();
        }
    }

    /**
     * Crea el proveedor con la conexión de un servidor concreto (panel WHM).
     */
    public static function forServer(Server $server): self
    {
        $provider = new self(null);
        $provider->baseUrl = $server->panelBaseUrl();
        $provider->whmUser = $server->panel_user ?: 'root';
        $provider->apiToken = $server->panel_token; // ya descifrado por el cast
        $provider->verifyTls = (bool) $server->panel_verify_tls;

        return $provider;
    }

    public function key(): string
    {
        return 'whm';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Hosting;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiToken);
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('WHM no configurado: falta base_url o api_token.');
        }

        // Llamada ligera de verificación (lista de paquetes).
        return $this->call('listpkgs', []);
    }

    /**
     * @param  array<string, mixed>  $data  domain, username, plan, password, contactemail...
     */
    public function createAccount(array $data): GatewayResult
    {
        return $this->call('createacct', $data);
    }

    public function suspend(string $username): GatewayResult
    {
        return $this->call('suspendacct', ['user' => $username]);
    }

    public function unsuspend(string $username): GatewayResult
    {
        return $this->call('unsuspendacct', ['user' => $username]);
    }

    public function changePackage(string $username, string $package): GatewayResult
    {
        return $this->call('changepackage', ['user' => $username, 'pkg' => $package]);
    }

    public function getAccountInfo(string $username): GatewayResult
    {
        return $this->call('accountsummary', ['user' => $username]);
    }

    public function getUsage(string $username): GatewayResult
    {
        return $this->call('showbw', ['searchtype' => 'user', 'search' => $username]);
    }

    /**
     * Lista las cuentas de correo del usuario cPanel usando la API de cPanel a
     * través de WHM (módulo Email, función list_pops_with_disk).
     */
    public function listEmailAccounts(string $cpanelUser): GatewayResult
    {
        $result = $this->call('cpanel', [
            'cpanel_jsonapi_user' => $cpanelUser,
            'cpanel_jsonapi_apiversion' => 2,
            'cpanel_jsonapi_module' => 'Email',
            'cpanel_jsonapi_func' => 'list_pops_with_disk',
        ]);

        if (! $result->success) {
            return $result;
        }

        $body = $result->data['body'] ?? [];
        // Respuesta cPanel API2 anidada bajo cpanelresult.data.
        $rows = $body['cpanelresult']['data'] ?? $body['result']['data'] ?? [];

        return GatewayResult::ok('WHM: correos OK', [
            'mailboxes' => $this->normalizeMailboxes(is_array($rows) ? $rows : []),
        ]);
    }

    /**
     * Normaliza las filas de list_pops_with_disk a la forma del contrato.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{email:string, disk_used_mb:float, disk_quota_mb:float|null, percent:int|null}>
     */
    private function normalizeMailboxes(array $rows): array
    {
        $mailboxes = [];

        foreach ($rows as $row) {
            $email = $row['email'] ?? trim(($row['login'] ?? '').'@'.($row['domain'] ?? ''), '@');
            if ($email === '') {
                continue;
            }

            $used = (float) ($row['_diskused'] ?? $row['diskused'] ?? 0);

            // cPanel usa 'unlimited' o 0 para cuota ilimitada según versión.
            $rawQuota = $row['_diskquota'] ?? $row['diskquota'] ?? null;
            $unlimited = $rawQuota === null
                || $rawQuota === 'unlimited'
                || (string) $rawQuota === '0'
                || (string) $rawQuota === '';
            $quota = $unlimited ? null : (float) $rawQuota;

            $percent = ($quota !== null && $quota > 0)
                ? (int) min(100, round($used / $quota * 100))
                : null;

            $mailboxes[] = [
                'email' => (string) $email,
                'disk_used_mb' => round($used, 2),
                'disk_quota_mb' => $quota !== null ? round($quota, 2) : null,
                'percent' => $percent,
            ];
        }

        usort($mailboxes, fn ($a, $b) => strcmp($a['email'], $b['email']));

        return $mailboxes;
    }

    /**
     * Ejecuta una función de la WHM API 1 (JSON).
     *
     * @param  array<string, mixed>  $params
     */
    private function call(string $function, array $params): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('WHM no configurado: falta base_url o api_token.');
        }

        $url = rtrim($this->baseUrl, '/').'/json-api/'.$function;

        try {
            $request = Http::withHeaders([
                'Authorization' => 'whm '.$this->whmUser.':'.$this->apiToken,
            ])
                ->timeout(20)
                ->retry(2, 300);

            // Certificados autofirmados: solo se omite la verificación si el
            // servidor lo indica (panel_verify_tls = false).
            if (! $this->verifyTls) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get($url, array_merge(['api.version' => 1], $params));

            $body = $response->json();

            // WHM API 1 devuelve metadata.result = 1 (ok) / 0 (error).
            $result = $body['metadata']['result'] ?? ($response->successful() ? 1 : 0);

            if (! $response->successful() || (int) $result !== 1) {
                $reason = $body['metadata']['reason'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('WHM: '.$reason, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('WHM: '.$function.' OK', [
                'http_status' => $response->status(),
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            Log::error('[WhmHostingProvider] '.$function.' failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con WHM: '.$e->getMessage());
        }
    }
}
