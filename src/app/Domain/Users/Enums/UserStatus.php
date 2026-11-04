<?php

namespace App\Domain\Users\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Blocked => 'Bloqueado',
        };
    }
}
