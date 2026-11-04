<?php

namespace App\Domain\Billing\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagada',
            self::Overdue => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Paid => 'bg-green-50 text-green-700',
            self::Overdue => 'bg-red-50 text-red-700',
            self::Cancelled => 'bg-slate-100 text-slate-600',
        };
    }

    /**
     * ¿Cuenta como "no pagada" para el resumen del cliente?
     */
    public function isUnpaid(): bool
    {
        return in_array($this, [self::Pending, self::Overdue], true);
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
