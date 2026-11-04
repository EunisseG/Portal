<?php

namespace App\Integrations\Providers\Monday;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationDispatchable;
use App\Integrations\Contracts\TicketSyncGateway;
use App\Integrations\DTO\TicketPayload;
use App\Integrations\Registry\IntegrationCapability;

/**
 * Proveedor FAKE de Monday para tickets. No hace llamadas reales: simula la
 * creación/actualización de items y mantiene la referencia externa en WORTEV.
 * Sirve para probar el flujo completo Ticket → evento → job → Monday sin
 * credenciales ni red.
 */
class FakeMondayTicketProvider implements TicketSyncGateway, IntegrationDispatchable
{
    use SyncsTicketReferences;

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'monday';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Tickets;
    }

    public function isConfigured(): bool
    {
        return $this->integration->is_enabled;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión simulada a Monday (fake).');
    }

    public function createItem(TicketPayload $ticket): GatewayResult
    {
        // ID/URL externos simulados y deterministas para pruebas.
        $externalId = 'MON-'.$ticket->ticketId;
        $externalUrl = 'https://monday.com/boards/fake/pulses/'.$externalId;

        return GatewayResult::ok('Item creado (fake).', [
            'external_id' => $externalId,
            'external_url' => $externalUrl,
        ]);
    }

    public function updateItem(string $externalId, TicketPayload $ticket): GatewayResult
    {
        return GatewayResult::ok('Item actualizado (fake).', ['external_id' => $externalId]);
    }

    public function changeStatus(string $externalId, string $status): GatewayResult
    {
        return GatewayResult::ok('Estado actualizado (fake).', ['external_id' => $externalId]);
    }
}
