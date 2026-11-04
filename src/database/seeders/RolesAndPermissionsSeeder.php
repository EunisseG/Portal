<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permisos granulares agrupados por dominio. Son independientes de los
     * roles: se pueden crear nuevos roles y recomponer sus permisos sin
     * modificar la arquitectura.
     *
     * @var list<string>
     */
    private array $permissions = [
        'users.view', 'users.create', 'users.update', 'users.block',
        'roles.manage', 'permissions.manage',
        'clients.view', 'clients.manage',
        'client_credit.manage',
        'sales_reps.manage',
        'services.view', 'services.manage',
        'plans.manage',
        'domains.view', 'domains.manage',
        'addons.manage', 'servers.manage',
        'invoices.view', 'invoices.manage',
        'payments.view', 'payments.record',
        'payment_proofs.manage',
        'tickets.view', 'tickets.manage', 'tickets.reply',
        'internal_tickets.view', 'internal_tickets.manage', 'internal_tickets.reply',
        'internal_areas.manage', 'employees.manage',
        'sla.manage',
        'news.view', 'news.manage',
        'kb.view', 'kb.manage',
        'notifications.manage',
        'audit.view',
        'security.view', 'security.manage',
        'integrations.manage',
        // Permisos del portal de cliente, asignables a sub-usuarios de un cliente.
        'client.invoices.view', 'client.proofs.upload',
        'client.tickets.view', 'client.tickets.create',
        'client.services.view', 'client.domains.view',
    ];

    /**
     * Permisos que un titular puede otorgar a su sub-usuario.
     *
     * @var list<string>
     */
    public const CLIENT_MEMBER_PERMISSIONS = [
        'client.invoices.view',
        'client.proofs.upload',
        'client.tickets.view',
        'client.tickets.create',
        'client.services.view',
        'client.domains.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Refresca la caché para que syncPermissions encuentre los permisos
        // recién creados en esta misma ejecución.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // super_admin: recibe todos los permisos vía Gate::before (ver AppServiceProvider),
        // pero también se crea el rol para poder asignarlo.
        Role::findOrCreate(RoleName::SuperAdmin->value, 'web');

        $admin = Role::findOrCreate(RoleName::Admin->value, 'web');
        $admin->syncPermissions($this->permissions);

        // staff: atención de tickets, alta de clientes y creación de pedidos
        // (servicios) con sus dominios y complementos. Puede consultar la base
        // de conocimiento y ver facturas (solo lectura), sin gestionar
        // facturación, usuarios, roles ni configuración del sistema.
        $staff = Role::findOrCreate(RoleName::Staff->value, 'web');
        $staff->syncPermissions([
            'tickets.view', 'tickets.manage', 'tickets.reply',
            'clients.view', 'clients.manage',
            'services.view', 'services.manage',
            'domains.view', 'domains.manage',
            'addons.manage',
            'kb.view',
            'invoices.view',
            'payment_proofs.manage',
        ]);

        $support = Role::findOrCreate(RoleName::Support->value, 'web');
        $support->syncPermissions([
            'clients.view',
            'services.view',
            'domains.view',
            'tickets.view', 'tickets.manage', 'tickets.reply',
            'internal_tickets.view', 'internal_tickets.manage', 'internal_tickets.reply',
            'sla.manage',
            'news.view', 'news.manage',
            'kb.view', 'kb.manage',
        ]);

        $billing = Role::findOrCreate(RoleName::Billing->value, 'web');
        $billing->syncPermissions([
            'clients.view',
            'client_credit.manage',
            'services.view',
            'invoices.view', 'invoices.manage',
            'payments.view', 'payments.record',
        ]);

        // client: sin permisos administrativos. Su acceso al área /client se
        // controla por el rol, no por permisos granulares.
        Role::findOrCreate(RoleName::Client->value, 'web');

        // employee: empleado interno. Sin permisos administrativos; su acceso
        // al portal interno (/empleados) se controla por el rol. Solo puede
        // levantar y seguir sus propios tickets internos.
        Role::findOrCreate(RoleName::Employee->value, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
