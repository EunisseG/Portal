<?php

namespace App\Domain\Tickets\Events;

use App\Domain\Tickets\Models\Ticket;
use App\Integrations\DTO\TicketPayload;
use App\Integrations\Events\IntegrationEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se emite cuando cambia el estado de un ticket. Cubre también el cierre
 * (cuando el nuevo estado es "closed"). Implementa IntegrationEvent.
 */
class TicketStatusChanged implements IntegrationEvent
{
    use Dispatchable;

    public function __construct(public readonly Ticket $ticket) {}

    public function eventName(): string
    {
        return 'TicketStatusChanged';
    }

    public function integrationPayload(): array
    {
        $this->ticket->loadMissing('client.user');

        return (new TicketPayload(
            ticketId: $this->ticket->id,
            number: (string) $this->ticket->number,
            subject: (string) $this->ticket->subject,
            status: $this->ticket->status?->value ?? '',
            priority: $this->ticket->priority?->value ?? '',
            clientName: $this->ticket->client?->user?->name,
        ))->toArray();
    }
}
