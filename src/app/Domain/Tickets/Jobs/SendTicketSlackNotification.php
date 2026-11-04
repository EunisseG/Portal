<?php

namespace App\Domain\Tickets\Jobs;

use App\Domain\Tickets\Models\Ticket;
use App\Integrations\Contracts\NotificationGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al equipo interno por Slack que un cliente abrió un nuevo ticket. Se
 * encola. Usa el NotificationGateway (Slack real si la integración está
 * configurada; si no, el fake que solo registra en el log).
 */
class SendTicketSlackNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue('integrations');
    }

    public function handle(NotificationGateway $notifications): void
    {
        $ticket = Ticket::with('client.user')->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $client = $ticket->client?->user?->name ?? 'Cliente';

        $notifications->notify(sprintf(
            "🎫 Nuevo ticket *%s* de %s\n*Asunto:* %s\n*Prioridad:* %s",
            $ticket->number,
            $client,
            $ticket->subject,
            $ticket->priority->value,
        ));
    }
}
