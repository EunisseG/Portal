<?php

namespace App\Domain\Services\Enums;

/**
 * Modo de cobro de un producto: pago único o recurrente por ciclo.
 */
enum BillingMode: string
{
    case OneTime = 'one_time';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::OneTime => 'Pago único',
            self::Recurring => 'Recurrente (por ciclo)',
        };
    }

    public function isRecurring(): bool
    {
        return $this === self::Recurring;
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
