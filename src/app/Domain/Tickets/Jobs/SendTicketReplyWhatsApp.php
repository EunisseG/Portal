<?php

namespace App\Domain\Tickets\Jobs;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Tickets\Models\Ticket;
use App\Integrations\Contracts\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa al cliente por WhatsApp que su ticket fue respondido, con invitación a
 * entrar al portal. Se encola. Usa el WhatsAppGateway (real si la integración
 * está configurada; si no, fake). Si el cliente no tiene número, no hace nada.
 */
class SendTicketReplyWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue('integrations');
    }

    public function handle(WhatsAppGateway $whatsapp): void
    {
        $ticket = Ticket::with('client.user')->find($this->ticketId);

        $number = $ticket?->client?->whatsapp;
        if (! $ticket || ! $number) {
            return;
        }

        // Nombre de plantilla configurable en la integración whatsapp.
        $integration = Integration::where('key', 'whatsapp')->first();
        $template = $integration?->config['template_ticket_reply'] ?? 'ticket_reply';

        $whatsapp->sendMessage($number, $template, [
            'name' => $ticket->client->user->name,
            'ticket' => $ticket->number,
            'url' => route('client.tickets.show', $ticket),
        ]);
    }
}
