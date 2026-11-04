<?php

namespace App\Integrations\Registry;

/**
 * Capacidades que puede cubrir una integración. Determina qué contrato
 * implementa su provider y qué operaciones expone.
 */
enum IntegrationCapability: string
{
    case Mail = 'mail';
    case Tickets = 'tickets';
    case Hosting = 'hosting';
    case Domains = 'domains';
    case Payments = 'payments';
    case Notifications = 'notifications';
    case Rest = 'rest';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Correo electrónico',
            self::Tickets => 'Gestión de tickets',
            self::Hosting => 'Hosting',
            self::Domains => 'Dominios',
            self::Payments => 'Pagos',
            self::Notifications => 'Notificaciones',
            self::Rest => 'API REST',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
