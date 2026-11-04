<?php

namespace App\Notifications;

use App\Domain\Tickets\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación in-app (canal database) cuando el staff responde un ticket.
 * Alimenta la campana del área de cliente.
 */
class TicketRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Ticket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket',
            'title' => 'Respuesta a tu ticket',
            'message' => $this->ticket->number.' · '.$this->ticket->subject,
            'url' => route('client.tickets.show', $this->ticket),
        ];
    }
}
