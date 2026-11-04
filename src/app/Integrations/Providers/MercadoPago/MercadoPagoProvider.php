<?php

namespace App\Integrations\Providers\MercadoPago;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor REAL de pagos con Mercado Pago (API v1).
 *
 * En este portal el uso principal es generar una preferencia de cobro
 * (Checkout Pro) para que el cliente pague en línea y confirmar el estado
 * vía webhook + consulta a la API.
 *
 * Config (integración 'mercadopago'):
 *   - config: (ninguno obligatorio; opcional 'currency' por defecto MXN)
 *   - secret: access_token (cifrado), webhook_secret (cifrado, para firmar webhooks)
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin access_token
 * devuelve fail sin llamar a la API.
 */
class MercadoPagoProvider implements PaymentGateway, IntegrationProvider
{
    private const BASE = 'https://api.mercadopago.com';

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'mercadopago';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Payments;
    }

    public function isConfigured(): bool
    {
        return $this->accessToken() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Mercado Pago no configurado: falta access_token.');
        }

        // Consulta ligera: métodos de pago disponibles de la cuenta.
        return $this->request('get', '/v1/payment_methods');
    }

    /**
     * Crea una preferencia de Checkout Pro. Espera:
     *   amount (float), title (string), reference (string, external_reference),
     *   currency (opcional), success_url/failure_url (opcional).
     *
     * @param  array<string, mixed>  $data
     */
    public function createCharge(array $data): GatewayResult
    {
        $reference = (string) ($data['reference'] ?? 'WORTEV-'.Str::upper(Str::random(10)));

        $payload = [
            'items' => [[
                'title' => (string) ($data['title'] ?? 'Servicio WORTEV'),
                'quantity' => 1,
                'unit_price' => (float) ($data['amount'] ?? 0),
                'currency_id' => (string) ($data['currency'] ?? $this->currency()),
            ]],
            'external_reference' => $reference,
        ];

        if (! empty($data['success_url']) || ! empty($data['failure_url'])) {
            $payload['back_urls'] = array_filter([
                'success' => $data['success_url'] ?? null,
                'failure' => $data['failure_url'] ?? null,
                'pending' => $data['pending_url'] ?? null,
            ]);
        }

        $result = $this->request('post', '/checkout/preferences', $payload);

        if (! $result->success) {
            return $result;
        }

        $body = $result->data['body'] ?? [];

        return GatewayResult::ok('Preferencia de pago creada en Mercado Pago.', [
            'reference' => $reference,
            'preference_id' => $body['id'] ?? null,
            'checkout_url' => $body['init_point'] ?? ($body['sandbox_init_point'] ?? null),
        ]);
    }

    public function getStatus(string $reference): GatewayResult
    {
        // Busca pagos por external_reference.
        $result = $this->request('get', '/v1/payments/search', ['external_reference' => $reference]);

        if (! $result->success) {
            return $result;
        }

        $payment = data_get($result->data, 'body.results.0');

        return GatewayResult::ok('Estado consultado en Mercado Pago.', [
            'reference' => $reference,
            'status' => $payment['status'] ?? 'unknown',
            'payment_id' => $payment['id'] ?? null,
        ]);
    }

    /**
     * Consulta un pago por su id (lo usa el webhook para obtener su
     * external_reference y estado). Devuelve data: external_reference, status.
     */
    public function getPayment(string $paymentId): GatewayResult
    {
        $result = $this->request('get', '/v1/payments/'.$paymentId);

        if (! $result->success) {
            return $result;
        }

        $body = $result->data['body'] ?? [];

        return GatewayResult::ok('Pago consultado en Mercado Pago.', [
            'payment_id' => $paymentId,
            'external_reference' => $body['external_reference'] ?? null,
            'status' => $body['status'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function request(string $method, string $path, array $params = []): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Mercado Pago no configurado: falta access_token.');
        }

        try {
            $http = Http::withToken($this->accessToken())
                ->timeout(20)
                ->retry(2, 300);

            $response = $method === 'get'
                ? $http->get(self::BASE.$path, $params)
                : $http->post(self::BASE.$path, $params);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $message = $body['message'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('Mercado Pago: '.$message, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('OK', ['http_status' => $response->status(), 'body' => $body]);
        } catch (\Throwable $e) {
            Log::error('[MercadoPagoProvider] request failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Mercado Pago: '.$e->getMessage());
        }
    }

    private function currency(): string
    {
        return $this->integration->config['currency'] ?? 'MXN';
    }

    private function accessToken(): ?string
    {
        return $this->integration->secrets()->where('key', 'access_token')->first()?->reveal();
    }
}
