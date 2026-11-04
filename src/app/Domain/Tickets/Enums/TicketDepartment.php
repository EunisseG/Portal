<?php

namespace App\Domain\Tickets\Enums;

enum TicketDepartment: string
{
    case Support = 'support';
    case Sales = 'sales';
    case Billing = 'billing';

    public function label(): string
    {
        return match ($this) {
            self::Support => 'Soporte',
            self::Sales => 'Ventas',
            self::Billing => 'Facturación',
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
