<?php

namespace App\Domain\Auth\Enums;

/**
 * Roles del sistema. Los permisos son independientes de los roles
 * (se asignan vía spatie/laravel-permission), de modo que se pueden
 * crear nuevos roles sin modificar la arquitectura.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Staff = 'staff';
    case Support = 'support';
    case Billing = 'billing';
    case Client = 'client';
    case Employee = 'employee';

    /**
     * Roles que pertenecen al área administrativa (/admin).
     *
     * @return list<self>
     */
    public static function adminRoles(): array
    {
        return [self::SuperAdmin, self::Admin, self::Staff, self::Support, self::Billing];
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrador',
            self::Admin => 'Administrador',
            self::Staff => 'Staff',
            self::Support => 'Soporte',
            self::Billing => 'Facturación',
            self::Client => 'Cliente',
            self::Employee => 'Empleado',
        };
    }

    public function isAdminArea(): bool
    {
        return in_array($this, self::adminRoles(), true);
    }
}
