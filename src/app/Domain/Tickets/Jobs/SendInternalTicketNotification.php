<?php

namespace App\Domain\Tickets\Jobs;

use App\Domain\Tickets\Models\Ticket;
use App\Integrations\Contracts\MailGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa por correo a los agentes del área interna cuando un empleado levanta un
 * ticket interno dirigido a ella. Se encola. Usa el MailGateway (en producción
 * sale por SMTP; en desarrollo queda registrado/al buzón de pruebas).
 */
class SendInternalTicketNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $ticketId)
    {
        $this->onQueue('integrations');
    }

    public function handle(MailGateway $mail): void
    {
        $ticket = Ticket::with(['internalArea', 'openedBy'])->find($this->ticketId);

        if (! $ticket || ! $ticket->isInternal() || ! $ticket->internalArea) {
            return;
        }

        $recipients = $ticket->internalArea->notifyEmailList();
        if ($recipients === []) {
            return;
        }

        $subject = sprintf('Nuevo ticket interno %s · %s', $ticket->number, $ticket->subject);
        $body = implode("\n", [
            'Se ha levantado un nuevo ticket interno para el área '.$ticket->internalArea->name.'.',
            '',
            'Número: '.$ticket->number,
            'Asunto: '.$ticket->subject,
            'Empleado: '.($ticket->openedBy?->fullName() ?: $ticket->openedBy?->name ?: '—'),
            'Prioridad: '.$ticket->priority->label(),
            'Criticidad: '.($ticket->criticality?->label() ?? '—'),
            '',
            'Atiéndelo desde el panel: Soporte → Tickets internos.',
        ]);

        foreach ($recipients as $to) {
            $mail->send($to, $subject, $body, ['kind' => 'internal_ticket', 'audience' => 'agent']);
        }
    }
}
