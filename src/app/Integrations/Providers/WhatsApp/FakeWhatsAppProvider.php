<?php

namespace App\Integrations\Providers\WhatsApp;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Contracts\WhatsAppGateway;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor FAKE de WhatsApp para desarrollo y pruebas. No envía mensajes
 * reales: registra la intención en el log y responde éxito. Simula el mismo
 * contrato que el provider real (Cloud API).
 */
class FakeWhatsAppProvider implements WhatsAppGateway, IntegrationProvider
{
    public function __construct(protected readonly ?Integration $integration = null) {}

    public function key(): string
    {
        return 'whatsapp';
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
        return GatewayResult::ok('Conexión de WhatsApp simulada (fake).');
    }

    public function sendMessage(string $to, string $template, array $params = []): GatewayResult
    {
        Log::info('[FakeWhatsAppProvider] sendMessage', compact('to', 'template', 'params'));

        return GatewayResult::ok('Mensaje de WhatsApp simulado (fake).', ['to' => $to, 'template' => $template]);
    }
}
