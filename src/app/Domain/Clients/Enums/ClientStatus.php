<?php

namespace App\Domain\Clients\Enums;

/**
 * Estado administrativo de una cuenta de cliente. Independiente del estado del
 * usuario titular (que controla el acceso), aunque suspender/cancelar también
 * bloquea el acceso.
 */
enum ClientStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Suspended => 'Suspendido',
            self::Cancelled => 'Cancelado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-green-100 text-green-700',
            self::Suspended => 'bg-red-100 text-red-700',
            self::Cancelled => 'bg-slate-200 text-slate-600',
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
