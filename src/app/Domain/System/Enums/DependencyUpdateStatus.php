<?php

namespace App\Domain\System\Enums;

/** Estado de una operación sobre dependencias. */
enum DependencyUpdateStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Running => 'En curso',
            self::Succeeded => 'Completada',
            self::Failed => 'Fallida',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Queued => 'bg-slate-100 text-slate-700',
            self::Running => 'bg-blue-100 text-blue-800',
            self::Succeeded => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
