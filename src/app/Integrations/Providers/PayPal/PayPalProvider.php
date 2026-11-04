<?php

namespace App\Integrations\Providers\PayPal;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\PaymentGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proveedor REAL de pagos con PayPal (Orders API v2).
 *
 * Autenticación OAuth2 client_credentials: se pide un access_token con
 * client_id + secret y luego se crea una orden de pago.
 *
 * Config (integración 'paypal'):
 *   - config: client_id, sandbox (bool), currency (opcional, def USD)
 *   - secret: client_secret (cifrado), webhook_id (cifrado, verificación de webhook)
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin client_id/secret
 * devuelve fail sin llamar a la API.
 */
class PayPalProvider implements PaymentGateway, IntegrationProvider
{
    private const PRODUCTION = 'https://api-m.paypal.com';

    private const SANDBOX = 'https://api-m.sandbox.paypal.com';

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'paypal';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Payments;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->clientId()) && $this->clientSecret() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('PayPal no configurado: falta client_id o client_secret.');
        }

        $token = $this->accessToken();

        return $token === null
            ? GatewayResult::fail('PayPal: no se pudo obtener el token de acceso.')
            : GatewayResult::ok('Conexión con PayPal correcta.');
    }

    /**
     * Crea una orden de pago. Espera:
     *   amount (float), reference (string), currency (opcional), return_url/cancel_url (opcional).
     *
     * @param  array<string, mixed>  $data
     */
    public function createCharge(array $data): GatewayResult
    {
        $token = $this->accessToken();

        if ($token === null) {
            return GatewayResult::fail('PayPal: no se pudo obtener el token de acceso.');
        }

        $reference = (string) ($data['reference'] ?? 'WORTEV-'.Str::upper(Str::random(10)));

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'amount' => [
                    'currency_code' => (string) ($data['currency'] ?? $this->currency()),
                    'value' => number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
                ],
            ]],
        ];

        if (! empty($data['return_url']) || ! empty($data['cancel_url'])) {
            $payload['application_context'] = array_filter([
                'return_url' => $data['return_url'] ?? null,
                'cancel_url' => $data['cancel_url'] ?? null,
            ]);
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->retry(2, 300)
                ->post($this->baseUrl().'/v2/checkout/orders', $payload);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $message = $body['message'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('PayPal: '.$message, ['http_status' => $response->status()]);
            }

            $approveUrl = collect($body['links'] ?? [])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            return GatewayResult::ok('Orden de pago creada en PayPal.', [
                'reference' => $reference,
                'order_id' => $body['id'] ?? null,
                'status' => $body['status'] ?? null,
                'checkout_url' => $approveUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('[PayPalProvider] createCharge failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con PayPal: '.$e->getMessage());
        }
    }

    public function getStatus(string $reference): GatewayResult
    {
        // 'reference' aquí es el order id de PayPal.
        $token = $this->accessToken();

        if ($token === null) {
            return GatewayResult::fail('PayPal: no se pudo obtener el token de acceso.');
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->retry(2, 300)
                ->get($this->baseUrl().'/v2/checkout/orders/'.$reference);

            $body = $response->json() ?? [];

            if (! $response->successful()) {
                $message = $body['message'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('PayPal: '.$message, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('Estado consultado en PayPal.', [
                'reference' => $reference,
                'status' => $body['status'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error('[PayPalProvider] getStatus failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con PayPal: '.$e->getMessage());
        }
    }

    /**
     * Obtiene un access_token OAuth2 (client_credentials). Devuelve null si falla.
     */
    private function accessToken(): ?string
    {
        try {
            $response = Http::withBasicAuth($this->clientId(), $this->clientSecret())
                ->asForm()
                ->timeout(20)
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $response->successful()) {
                return null;
            }

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::error('[PayPalProvider] token failed', ['error' => $e->getMessage()]);

            return null;
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
        return $this->integration->config['currency'] ?? 'USD';
    }

    private function clientId(): ?string
    {
        return $this->integration->config['client_id'] ?? null;
    }

    private function clientSecret(): ?string
    {
        return $this->integration->secrets()->where('key', 'client_secret')->first()?->reveal();
    }
}
