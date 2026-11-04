<?php

namespace App\Support;

/**
 * Lista corta de países (ISO alpha-2 => nombre) para los selects del portal.
 * México primero. Si se necesitan más, se agregan aquí.
 */
final class Countries
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'MX' => 'México',
            'US' => 'Estados Unidos',
            'CA' => 'Canadá',
            'GT' => 'Guatemala',
            'BZ' => 'Belice',
            'SV' => 'El Salvador',
            'HN' => 'Honduras',
            'NI' => 'Nicaragua',
            'CR' => 'Costa Rica',
            'PA' => 'Panamá',
            'CO' => 'Colombia',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'PE' => 'Perú',
            'BO' => 'Bolivia',
            'CL' => 'Chile',
            'AR' => 'Argentina',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'BR' => 'Brasil',
            'ES' => 'España',
            'OT' => 'Otro',
        ];
    }

    public static function name(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return self::options()[$code] ?? $code;
    }
}
