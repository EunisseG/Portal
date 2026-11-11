<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos propios para secciones que antes "tomaban prestado" otro permiso:
 *  - reports.view       (Gráficas)          antes: invoices.view
 *  - currencies.manage  (Tipos de moneda)   antes: plans.manage
 *  - surveys.manage     (Encuestas)         antes: sla.manage
 *  - settings.manage    (Apariencia, plantillas de correo, Modo de operación
 *                        y SMTP)            antes: integrations.manage
 *
 * Para no quitarle el acceso a nadie, cada permiso nuevo se otorga a los roles
 * (incluidos los personalizados) y usuarios que ya tenían el permiso anterior.
 * Después se puede retirar desde Roles y permisos.
 */
return new class extends Migration
{
    /** @var array<string, string> nuevo => anterior */
    private const MAP = [
        'reports.view' => 'invoices.view',
        'currencies.manage' => 'plans.manage',
        'surveys.manage' => 'sla.manage',
        'settings.manage' => 'integrations.manage',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MAP as $new => $old) {
            $permission = Permission::findOrCreate($new, 'web');
            $source = Permission::where('name', $old)->where('guard_name', 'web')->first();

            if (! $source) {
                continue;
            }

            foreach (Role::whereHas('permissions', fn ($q) => $q->whereKey($source->id))->get() as $role) {
                $role->givePermissionTo($permission);
            }

            // Usuarios con el permiso anterior asignado directamente.
            $userIds = \Illuminate\Support\Facades\DB::table(config('permission.table_names.model_has_permissions'))
                ->where('permission_id', $source->id)
                ->where('model_type', \App\Models\User::class)
                ->pluck('model_id');

            foreach (\App\Models\User::whereIn('id', $userIds)->get() as $user) {
                $user->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys(self::MAP))->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
