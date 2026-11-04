<?php

namespace App\Integrations\Providers\Monday;

use App\Domain\Integrations\Models\IntegrationReference;
use App\Domain\Tickets\Models\Ticket;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\DTO\TicketPayload;

/**
 * Lógica compartida entre el provider real y el fake de Monday: mapea el evento
 * de dominio a la operación (crear/actualizar item o cambiar estado) y mantiene
 * la IntegrationReference (Ticket ↔ item externo) en WORTEV, que sigue siendo
 * la fuente de verdad.
 *
 * Requiere que la clase use $this->integration y las operaciones createItem/
 * changeStatus del contrato TicketSyncGateway.
 */
trait SyncsTicketReferences
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleEvent(string $eventName, array $payload): GatewayResult
    {
        $ticket = TicketPayload::fromArray($payload);

        $reference = IntegrationReference::firstOrNew([
            'integration_id' => $this->integration->id,
            'referenceable_type' => (new Ticket())->getMorphClass(),
            'referenceable_id' => $ticket->ticketId,
        ]);

        if (! $reference->exists) {
            $result = $this->createItem($ticket);
            if ($result->success) {
                $reference->external_id = $result->data['external_id'] ?? null;
                $reference->external_url = $result->data['external_url'] ?? null;
            }
        } else {
            $result = $this->changeStatus($reference->external_id, $ticket->status);
        }

        $reference->sync_status = $result->success ? 'synced' : 'error';
        $reference->last_synced_at = now();
        $reference->save();

        if (! $result->success) {
            return GatewayResult::fail($result->message, [
                'external_reference' => $reference->external_id ? 'MondayItem:'.$reference->external_id : null,
            ]);
        }

        return GatewayResult::ok('Sincronizado con Monday.', [
            'external_reference' => 'MondayItem:'.$reference->external_id,
        ]);
    }
}
