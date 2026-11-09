<?php

namespace App\Domain\System\Enums;

/** Tipo de operación sobre dependencias lanzada desde el panel. */
enum DependencyUpdateType: string
{
    case Update = 'update';                   // respalda y actualiza paquetes vulnerables
    case RestoreCode = 'restore_code';         // vuelve vendor/lockfiles/assets a un respaldo
    case RestoreDatabase = 'restore_database'; // vuelve la base de datos a un respaldo

    public function label(): string
    {
        return match ($this) {
            self::Update => 'Actualización',
            self::RestoreCode => 'Restauración de código',
            self::RestoreDatabase => 'Restauración de base de datos',
        };
    }
}
