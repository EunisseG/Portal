<?php

namespace App\Integrations\Webhooks\Handlers;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Webhooks\WebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Handler de webhooks de PayPal.
 *
 * PayPal no usa HMAC simple: la verificación se hace llamando a su API
 * (/v1/notifications/verify-webhook-signature) con las cabeceras de
 * transmisión del webhook y el 'webhook_id' configurado. Requiere un
 * access_token OAuth2 (client_id + client_secret de la integración 'paypal').
 *
 * Confirmación de pago: el evento (p.ej. CHECKOUT.ORDER.APPROVED) queda en el
 * buzón; WORTEV sigue siendo la fuente de verdad.
 */
class PayPalWebhookHandler implements WebhookHandler
{
    private const PRODUCTION = 'https://api-m.paypal.com';

    private const SANDBOX = 'https://api-m.sandbox.paypal.com';

    public function provider(): string
    {
        return 'paypal';
    }

    public function verifySignature(Request $request): bool
    {
        $integration = Integration::where('key', 'paypal')->first();

        if (! $integration) {
            return false;
        }

        $webhookId = $integration->secrets()->where('key', 'webhook_id')->first()?->reveal();
        $clientId = $integration->config['client_id'] ?? null;
        $clientSecret = $integration->secrets()->where('key', 'client_secret')->first()?->reveal();

        if (empty($webhookId) || empty($clientId) || empty($clientSecret)) {
            return false;
        }

        $base = ($integration->config['sandbox'] ?? false) ? self::SANDBOX : self::PRODUCTION;

        try {
            $tokenResponse = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->timeout(15)
                ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            $token = $tokenResponse->json('access_token');

            if (! $tokenResponse->successful() || ! $token) {
                return false;
            }

            $verify = Http::withToken($token)
                ->timeout(15)
                ->post($base.'/v1/notifications/verify-webhook-signature', [
                    'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url' => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->json()->all(),
                ]);

            return $verify->successful()
                && ($verify->json('verification_status') === 'SUCCESS');
        } catch (\Throwable $e) {
            Log::error('[PayPalWebhookHandler] verify failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function externalEventId(Request $request): string
    {
        return (string) $request->input('id', $request->header('PAYPAL-TRANSMISSION-ID', ''));
    }

    public function process(array $payload): void
    {
        // Punto de extensión: conciliar la orden (CHECKOUT.ORDER.APPROVED, etc.).
        // Por defecto no muta datos maestros de WORTEV.
    }
}
