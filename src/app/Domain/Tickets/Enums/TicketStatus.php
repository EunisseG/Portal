<?php

namespace App\Domain\Tickets\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Answered = 'answered';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierto',
            self::Pending => 'En espera',
            self::Answered => 'Respondido',
            self::Resolved => 'Resuelto',
            self::Closed => 'Cerrado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-50 text-blue-700',
            self::Pending => 'bg-amber-50 text-amber-700',
            self::Answered => 'bg-green-50 text-green-700',
            self::Resolved => 'bg-emerald-50 text-emerald-700',
            self::Closed => 'bg-slate-100 text-slate-600',
        };
    }

    /** Estados que cuentan como "activos" (todo menos Cerrado). */
    public function isActive(): bool
    {
        return $this !== self::Closed;
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
