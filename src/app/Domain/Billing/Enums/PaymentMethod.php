<?php

namespace App\Domain\Billing\Enums;

/**
 * Método de pago reportado por el cliente en su comprobante.
 */
enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Deposit = 'deposit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transferencia',
            self::Cash => 'Efectivo',
            self::Card => 'Tarjeta',
            self::Deposit => 'Depósito',
            self::Other => 'Otro',
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
