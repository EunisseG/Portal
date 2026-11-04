<?php

namespace App\Domain\Services\Enums;

/**
 * Gestor/panel de control del servidor de hosting. Determina qué adaptador
 * (provider) se usa para aprovisionar cuentas.
 */
enum ServerPanel: string
{
    case Whm = 'whm';
    case Plesk = 'plesk';

    public function label(): string
    {
        return match ($this) {
            self::Whm => 'WHM / cPanel',
            self::Plesk => 'Plesk',
        };
    }

    /** Puerto por defecto del panel (para sugerirlo en el formulario). */
    public function defaultPort(): int
    {
        return match ($this) {
            self::Whm => 2087,
            self::Plesk => 8443,
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
