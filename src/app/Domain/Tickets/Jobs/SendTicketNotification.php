<?php

namespace App\Domain\Tickets\Jobs;

use App\Domain\Tickets\Enums\TicketEvent;
use App\Domain\Tickets\Models\Ticket;
use App\Integrations\Contracts\MailGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica por correo al equipo de infraestructura cuando un ticket se abre o
 * se responde. Se encola. El envío usa el MailGateway (hoy fake: queda en log).
 */
class SendTicketNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $ticketId,
        public readonly TicketEvent $event,
    ) {}

    public function handle(MailGateway $mail): void
    {
        $ticket = Ticket::with('client.user')->find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $to = config('tickets.notify_email');

        $subject = sprintf(
            '[%s] %s — %s',
            $ticket->number,
            $this->event->subjectLabel(),
            $ticket->subject,
        );

        $body = implode("\n", [
            $this->event->description(),
            '',
            'Ticket: '.$ticket->number,
            'Asunto: '.$ticket->subject,
            'Cliente: '.$ticket->client?->user?->name.' ('.$ticket->client?->user?->email.')',
            'Departamento: '.$ticket->department?->label(),
            'Prioridad: '.$ticket->priority?->label(),
            'Estado: '.$ticket->status?->label(),
        ]);

        $mail->send($to, $subject, $body, [
            'ticket_id' => $ticket->id,
            'event' => $this->event->value,
        ]);
    }
}
