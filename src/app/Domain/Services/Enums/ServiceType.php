<?php

namespace App\Domain\Services\Enums;

enum ServiceType: string
{
    case Hosting = 'hosting';
    case Email = 'email';
    case License = 'license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting',
            self::Email => 'Correo',
            self::License => 'Licencia',
            self::Other => 'Otro',
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
