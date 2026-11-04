<?php

namespace App\Domain\Billing\Enums;

/**
 * Estado de una orden de pago en línea (folio de cobro). NO confundir con la
 * factura CFDI: la orden es solo el intento de cobro por pasarela.
 */
enum PaymentOrderStatus: string
{
    case Pending = 'pending';     // generada, esperando pago
    case Paid = 'paid';           // pagada (conciliada por webhook)
    case Expired = 'expired';     // caducó sin pagarse (5 días)
    case Cancelled = 'cancelled'; // cancelada manualmente

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagada',
            self::Expired => 'Expirada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Paid => 'bg-green-50 text-green-700',
            self::Expired => 'bg-slate-100 text-slate-600',
            self::Cancelled => 'bg-red-50 text-red-700',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
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
