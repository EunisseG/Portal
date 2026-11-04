<?php

namespace App\Integrations\Providers\Slack;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\NotificationGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de notificaciones internas con Slack (Incoming Webhook).
 *
 * Un Incoming Webhook de Slack es una URL secreta a la que se hace POST con
 * un JSON {"text": "..."}. Un 200 con cuerpo "ok" indica éxito.
 *
 * Config (integración 'slack'):
 *   - config: channel (opcional, informativo), username (opcional), icon_emoji (opcional)
 *   - secret: webhook_url (cifrado)
 *
 * "Listo para conectar": las llamadas HTTP son reales; sin webhook_url
 * devuelve fail sin llamar a Slack.
 */
class SlackProvider implements NotificationGateway, IntegrationProvider
{
    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'slack';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Notifications;
    }

    public function isConfigured(): bool
    {
        return $this->webhookUrl() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Slack no configurado: falta webhook_url.');
        }

        return $this->notify('✅ Conexión de prueba desde WORTEV.');
    }

    /**
     * @param  array<string, mixed>  $options  channel, username, icon_emoji...
     */
    public function notify(string $message, array $options = []): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Slack no configurado: falta webhook_url.');
        }

        $payload = array_filter([
            'text' => $message,
            'channel' => $options['channel'] ?? $this->config('channel'),
            'username' => $options['username'] ?? $this->config('username'),
            'icon_emoji' => $options['icon_emoji'] ?? $this->config('icon_emoji'),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::timeout(15)
                ->retry(2, 300)
                ->post($this->webhookUrl(), $payload);

            if (! $response->successful()) {
                return GatewayResult::fail('Slack: HTTP '.$response->status(), [
                    'http_status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return GatewayResult::ok('Notificación enviada a Slack.', [
                'http_status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[SlackProvider] notify failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Slack: '.$e->getMessage());
        }
    }

    private function config(string $key): ?string
    {
        return $this->integration->config[$key] ?? null;
    }

    private function webhookUrl(): ?string
    {
        return $this->integration->secrets()->where('key', 'webhook_url')->first()?->reveal();
    }
}
