<?php

namespace App\Integrations\Providers\Openpay;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor REAL de pagos con Openpay (API REST v1).
 *
 * Autenticación Basic: usuario = private key, contraseña vacía. Todas las
 * rutas cuelgan de /v1/{merchant_id}. Para cobros sin tarjeta previa usamos
 * el método "store" (referencia de pago en tienda) que no requiere tokenizar
 * tarjeta ni PCI, adecuado para "listo para conectar".
 *
 * Config (integración 'openpay'):
 *   - config: merchant_id, sandbox (bool)
 *   - secret: private_key (cifrado), webhook_secret (cifrado)
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin merchant_id o
 * private_key devuelve fail sin llamar a la API.
 */
class OpenpayProvider implements PaymentGateway, IntegrationProvider
{
    private const PRODUCTION = 'https://api.openpay.mx/v1';

    private const SANDBOX = 'https://sandbox-api.openpay.mx/v1';

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'openpay';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Payments;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->merchantId()) && $this->privateKey() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Openpay no configurado: falta merchant_id o private_key.');
        }

        // Consulta ligera: información del comercio.
        return $this->request('get', '');
    }

    /**
     * Crea un cargo tipo "store" (referencia de pago en efectivo). Espera:
     *   amount (float), description (string), reference (string), currency (opcional).
     *
     * @param  array<string, mixed>  $data
     */
    public function createCharge(array $data): GatewayResult
    {
        $reference = (string) ($data['reference'] ?? 'WORTEV-'.Str::upper(Str::random(10)));

        $payload = [
            'method' => 'store',
            'amount' => (float) ($data['amount'] ?? 0),
            'description' => (string) ($data['description'] ?? 'Servicio WORTEV'),
            'order_id' => $reference,
            'currency' => (string) ($data['currency'] ?? $this->currency()),
        ];

        $result = $this->request('post', '/charges', $payload);

        if (! $result->success) {
            return $result;
        }

        $body = $result->data['body'] ?? [];

        return GatewayResult::ok('Cargo creado en Openpay.', [
            'reference' => $reference,
            'charge_id' => $body['id'] ?? null,
            'status' => $body['status'] ?? null,
            'payment_reference' => data_get($body, 'payment_method.reference'),
            'barcode_url' => data_get($body, 'payment_method.barcode_url'),
        ]);
    }

    public function getStatus(string $reference): GatewayResult
    {
        // 'reference' aquí es el charge id de Openpay.
        $result = $this->request('get', '/charges/'.$reference);

        if (! $result->success) {
            return $result;
        }

        $body = $result->data['body'] ?? [];

        return GatewayResult::ok('Estado consultado en Openpay.', [
            'reference' => $reference,
            'status' => $body['status'] ?? 'unknown',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Openpay no configurado: falta merchant_id o private_key.');
        }

        $url = $this->baseUrl().'/'.$this->merchantId().$path;

        try {
            $http = Http::withBasicAuth($this->privateKey(), '')
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 300);

            $response = $method === 'get'
                ? $http->get($url)
                : $http->post($url, $payload);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                // Openpay devuelve {"description": "...", "error_code": ...}.
                $message = $body['description'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('Openpay: '.$message, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('OK', ['http_status' => $response->status(), 'body' => $body]);
        } catch (\Throwable $e) {
            Log::error('[OpenpayProvider] request failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Openpay: '.$e->getMessage());
        }
    }

    private function baseUrl(): string
    {
        return $this->sandbox() ? self::SANDBOX : self::PRODUCTION;
    }

    private function sandbox(): bool
    {
        return (bool) ($this->integration->config['sandbox'] ?? false);
    }

    private function currency(): string
    {
        return $this->integration->config['currency'] ?? 'MXN';
    }

    private function merchantId(): ?string
    {
        return $this->integration->config['merchant_id'] ?? null;
    }

    private function privateKey(): ?string
    {
        return $this->integration->secrets()->where('key', 'private_key')->first()?->reveal();
    }
}
