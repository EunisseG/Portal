<?php

namespace App\Domain\Tickets\Enums;

/**
 * Tipo de ticket: de CLIENTE (levantado desde el portal del cliente) o INTERNO
 * (levantado por un empleado de la empresa desde el portal interno).
 */
enum TicketType: string
{
    case Client = 'client';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Cliente',
            self::Internal => 'Interno',
        };
    }
}
