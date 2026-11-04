<?php

namespace App\Domain\Billing\Enums;

/**
 * Estado de revisión de un comprobante de pago enviado por el cliente.
 */
enum PaymentProofStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Approved => 'bg-green-50 text-green-700',
            self::Rejected => 'bg-red-50 text-red-700',
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
