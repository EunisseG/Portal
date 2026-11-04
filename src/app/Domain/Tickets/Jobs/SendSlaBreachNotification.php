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
 * Avisa al equipo interno por Slack que un ticket incumplió su SLA (lo dispara
 * el comando `sla:check`). Se encola. Usa el NotificationGateway (Slack real
 * si la integración está configurada; si no, el fake que solo registra).
 */
class SendSlaBreachNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>  $kinds  Tipos de SLA incumplidos ('primera respuesta', 'resolución').
     */
    public function __construct(
        public readonly int $ticketId,
        public readonly array $kinds = [],
    ) {
        $this->onQueue('integrations');
    }

    public function handle(NotificationGateway $notifications): void
    {
        $ticket = Ticket::with('client.user')->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $client = $ticket->client?->user?->name ?? 'Cliente';
        $kindLabel = $this->kinds === [] ? 'SLA' : implode(' y ', $this->kinds);

        $notifications->notify(sprintf(
            "⏰ SLA incumplido en ticket *%s* de %s\n*Asunto:* %s\n*Incumple:* %s\n*Criticidad:* %s",
            $ticket->number,
            $client,
            $ticket->subject,
            $kindLabel,
            $ticket->criticality?->label() ?? 'Media',
        ));
    }
}
