<?php

namespace App\Domain\Tickets\Enums;

/**
 * Criticidad de un ticket: el impacto en el negocio del cliente. Es distinta de
 * la prioridad (ritmo de atención del equipo) y es la que define el SLA
 * aplicable (ver SlaPolicy).
 */
enum TicketCriticality: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítica',
            self::High => 'Alta',
            self::Medium => 'Media',
            self::Low => 'Baja',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Critical => 'bg-red-50 text-red-700',
            self::High => 'bg-orange-50 text-orange-700',
            self::Medium => 'bg-blue-50 text-blue-700',
            self::Low => 'bg-slate-100 text-slate-600',
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
