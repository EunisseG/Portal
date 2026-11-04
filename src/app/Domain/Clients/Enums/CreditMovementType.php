<?php

namespace App\Domain\Clients\Enums;

/**
 * Tipo de movimiento del saldo a favor: abono (suma) o cargo (resta).
 */
enum CreditMovementType: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Abono',
            self::Debit => 'Cargo',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Credit => 'bg-green-50 text-green-700',
            self::Debit => 'bg-amber-50 text-amber-700',
        };
    }

    /** Signo del movimiento sobre el saldo. */
    public function sign(): int
    {
        return $this === self::Credit ? 1 : -1;
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
