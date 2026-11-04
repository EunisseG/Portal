<?php

namespace App\Domain\Services\Enums;

/**
 * Tipo de producto del catálogo.
 */
enum ProductType: string
{
    case Hosting = 'hosting';
    case Email = 'email';
    case License = 'license';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting',
            self::Email => 'Correo',
            self::License => 'Licencia',
            self::Custom => 'Custom',
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
