<?php

namespace App\Integrations\Providers\Plesk;

use App\Domain\Services\Models\Server;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\HostingGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de hosting vía la REST API de Plesk (normalmente en el
 * puerto 8443). Se autentica con un API token en el header X-API-Key.
 *
 * Cubre el alcance de Fase 1: probar conexión, crear cuenta (cliente + dominio)
 * y suspender/reactivar el dominio. Plesk organiza el hosting por "domains"
 * (sites), por eso suspender/reactivar opera sobre el dominio.
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin host/token devuelve
 * fail sin llamar a la API.
 */
class PleskHostingProvider implements HostingGateway
{
    private ?string $baseUrl = null;

    private ?string $apiToken = null;

    private bool $verifyTls = true;

    private ?string $userPrefix = null;

    private int $passwordLength = 16;

    public static function forServer(Server $server): self
    {
        $provider = new self();
        $provider->baseUrl = $server->panelBaseUrl();
        $provider->apiToken = $server->panel_token; // ya descifrado por el cast
        $provider->verifyTls = (bool) $server->panel_verify_tls;
        $provider->userPrefix = $server->panel_user_prefix;
        $provider->passwordLength = $server->panel_password_length ?: 16;

        return $provider;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl) && ! empty($this->apiToken);
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Plesk no configurado: falta host o token.');
        }

        // Endpoint ligero: información del servidor.
        return $this->request('get', '/api/v2/server');
    }

    /**
     * Crea una cuenta completa en Plesk: cliente + dominio con suscripción de
     * hosting (plan y login). En Plesk el hosting cuelga de un cliente y un
     * dominio; por eso primero se crea/asegura el cliente y luego el dominio.
     *
     * @param  array<string, mixed>  $data  domain, username, plan, contactemail, contactname...
     */
    public function createAccount(array $data): GatewayResult
    {
        $login = $this->buildLogin($data['username'] ?? ($data['domain'] ?? 'cliente'));
        $password = $this->generatePassword();

        // 1) Crear el cliente propietario de la suscripción.
        $clientResult = $this->request('post', '/api/v2/clients', [
            'name' => $data['contactname'] ?? ($data['domain'] ?? $login),
            'login' => $login,
            'password' => $password,
            'email' => $data['contactemail'] ?? null,
        ]);

        if (! $clientResult->success) {
            return $clientResult;
        }

        $clientId = data_get($clientResult->data, 'body.id');

        // 2) Crear el dominio con hosting, asignado al cliente y su plan.
        $domainPayload = [
            'name' => $data['domain'] ?? '',
            'hosting_type' => 'virtual',
            'owner_client' => ['id' => $clientId],
        ];

        if (! empty($data['plan'])) {
            $domainPayload['plan'] = ['name' => $data['plan']];
        }

        $domainResult = $this->request('post', '/api/v2/domains', $domainPayload);

        if (! $domainResult->success) {
            return $domainResult;
        }

        return GatewayResult::ok('Plesk: cuenta creada.', [
            'username' => $login,
            'client_id' => $clientId,
            'domain_id' => data_get($domainResult->data, 'body.id'),
        ]);
    }

    /** Construye un login válido (alfanumérico) con el prefijo del servidor. */
    private function buildLogin(string $base): string
    {
        $clean = preg_replace('/[^a-z0-9]/', '', strtolower($base)) ?: 'cliente';

        return substr(($this->userPrefix ?? '').$clean, 0, 32);
    }

    /** Contraseña aleatoria con la longitud configurada en el servidor. */
    private function generatePassword(): string
    {
        $length = max(8, min(64, $this->passwordLength));

        return \Illuminate\Support\Str::password($length, symbols: false);
    }

    public function suspend(string $username): GatewayResult
    {
        // En Plesk se suspende el dominio (site). 'username' aquí es el dominio.
        return $this->request('put', '/api/v2/domains/'.rawurlencode($username), [
            'enabled' => false,
        ]);
    }

    public function unsuspend(string $username): GatewayResult
    {
        return $this->request('put', '/api/v2/domains/'.rawurlencode($username), [
            'enabled' => true,
        ]);
    }

    public function changePackage(string $username, string $package): GatewayResult
    {
        return $this->request('put', '/api/v2/domains/'.rawurlencode($username), [
            'plan' => ['name' => $package],
        ]);
    }

    public function getAccountInfo(string $username): GatewayResult
    {
        return $this->request('get', '/api/v2/domains/'.rawurlencode($username));
    }

    public function getUsage(string $username): GatewayResult
    {
        return $this->request('get', '/api/v2/domains/'.rawurlencode($username).'/usage');
    }

    /**
     * Listado de buzones. Aún no implementado para Plesk; se devuelve un fallo
     * legible para que la UI muestre el aviso correspondiente.
     */
    public function listEmailAccounts(string $cpanelUser): GatewayResult
    {
        return GatewayResult::fail('El listado de correos no está disponible para servidores Plesk.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Plesk no configurado: falta host o token.');
        }

        $url = rtrim($this->baseUrl, '/').$path;

        try {
            $request = Http::withHeaders(['X-API-Key' => $this->apiToken])
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300);

            if (! $this->verifyTls) {
                $request = $request->withoutVerifying();
            }

            $response = match ($method) {
                'get' => $request->get($url),
                'post' => $request->post($url, $payload),
                'put' => $request->put($url, $payload),
                default => $request->get($url),
            };

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                // Plesk devuelve {"code":..., "message":"..."} en errores.
                $message = $body['message'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('Plesk: '.$message, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('Plesk: OK', ['http_status' => $response->status(), 'body' => $body]);
        } catch (\Throwable $e) {
            Log::error('[PleskHostingProvider] '.$path.' failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Plesk: '.$e->getMessage());
        }
    }
}
