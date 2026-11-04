<?php

namespace App\Integrations\Providers\Slack;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\NotificationGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor FAKE de Slack para desarrollo y pruebas (sin red). Registra la
 * notificación en el log en lugar de enviarla.
 */
class FakeSlackProvider implements NotificationGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

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
        return true;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión simulada con Slack (fake).');
    }

    public function notify(string $message, array $options = []): GatewayResult
    {
        Log::info('[FakeSlackProvider] notify', ['message' => $message, 'options' => $options]);

        return GatewayResult::ok('Notificación simulada a Slack (fake).', [
            'message' => $message,
        ]);
    }
}
