<?php

namespace App\Domain\Content\Enums;

enum MaintenanceStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::InProgress => 'En curso',
            self::Completed => 'Finalizado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Scheduled => 'bg-blue-50 text-blue-700',
            self::InProgress => 'bg-amber-50 text-amber-700',
            self::Completed => 'bg-slate-100 text-slate-600',
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
