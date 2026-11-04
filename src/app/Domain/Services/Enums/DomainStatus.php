<?php

namespace App\Domain\Services\Enums;

enum DomainStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Expired = 'expired';
    case Transferring = 'transferring';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Pending => 'Pendiente',
            self::Expired => 'Vencido',
            self::Transferring => 'En transferencia',
            self::Cancelled => 'Cancelado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-green-50 text-green-700',
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Expired => 'bg-red-50 text-red-700',
            self::Transferring => 'bg-blue-50 text-blue-700',
            self::Cancelled => 'bg-slate-100 text-slate-600',
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
