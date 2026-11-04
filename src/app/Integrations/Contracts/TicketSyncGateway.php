<?php

namespace App\Integrations\Contracts;

use App\Integrations\DTO\TicketPayload;

/**
 * Gestión de tickets en un sistema externo (Monday, Slack, etc.). WORTEV es la
 * fuente de verdad; el externo recibe una representación del ticket.
 */
interface TicketSyncGateway extends IntegrationProvider
{
    /** Crea el item externo. Devuelve external_id/external_url en data. */
    public function createItem(TicketPayload $ticket): GatewayResult;

    public function updateItem(string $externalId, TicketPayload $ticket): GatewayResult;

    public function changeStatus(string $externalId, string $status): GatewayResult;
}
