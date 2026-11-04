<?php

namespace App\Domain\Services\Enums;

enum ServiceStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Clases de color (Tailwind) para el badge de estado.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Active => 'bg-green-50 text-green-700',
            self::Suspended => 'bg-orange-50 text-orange-700',
            self::Cancelled => 'bg-red-50 text-red-700',
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
