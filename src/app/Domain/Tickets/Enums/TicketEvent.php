<?php

namespace App\Domain\Tickets\Enums;

/**
 * Evento del ticket que dispara una notificación por correo.
 */
enum TicketEvent: string
{
    case Opened = 'opened';
    case ClientReplied = 'client_replied';
    case StaffReplied = 'staff_replied';

    public function subjectLabel(): string
    {
        return match ($this) {
            self::Opened => 'Nuevo ticket',
            self::ClientReplied => 'Respuesta del cliente',
            self::StaffReplied => 'Respuesta de WORTEV',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Opened => 'Se abrió un nuevo ticket.',
            self::ClientReplied => 'El cliente respondió un ticket.',
            self::StaffReplied => 'WORTEV respondió un ticket.',
        };
    }
}
