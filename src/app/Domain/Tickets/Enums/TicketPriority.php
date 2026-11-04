<?php

namespace App\Domain\Tickets\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baja',
            self::Normal => 'Normal',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Low => 'bg-slate-100 text-slate-600',
            self::Normal => 'bg-blue-50 text-blue-700',
            self::High => 'bg-orange-50 text-orange-700',
            self::Urgent => 'bg-red-50 text-red-700',
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
