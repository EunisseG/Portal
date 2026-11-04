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
 * Invita al cliente a responder la encuesta de satisfacción cuando su ticket
 * se cierra. Envía por correo el enlace al ticket, donde aparece la encuesta.
 * Se encola. El envío usa el MailGateway (hoy fake: queda en log).
 */
class SendSurveyInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $ticketId) {}

    public function handle(MailGateway $mail): void
    {
        $ticket = Ticket::with('client.user')->find($this->ticketId);

        if (! $ticket || ! $ticket->client?->user?->email) {
            return;
        }

        $to = $ticket->client->user->email;
        $link = route('client.tickets.show', $ticket);

        $subject = sprintf('[%s] ¿Cómo fue nuestra atención?', $ticket->number);

        $body = implode("\n", [
            'Hola '.$ticket->client->user->name.',',
            '',
            'Tu ticket "'.$ticket->subject.'" ('.$ticket->number.') fue cerrado.',
            'Nos ayudaría mucho conocer tu opinión sobre la atención recibida.',
            '',
            'Responde la encuesta (1 a 5) desde aquí:',
            $link,
            '',
            'Gracias,',
            'Equipo WORTEV',
        ]);

        $mail->send($to, $subject, $body, [
            'ticket_id' => $ticket->id,
            'type' => 'survey_invitation',
        ]);
    }
}
