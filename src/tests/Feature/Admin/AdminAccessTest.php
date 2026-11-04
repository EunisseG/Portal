<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_can_open_users_and_clients_and_audit(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
        $this->actingAs($admin)->get('/admin/clientes')->assertOk();
        $this->actingAs($admin)->get('/admin/auditoria')->assertOk();
    }

    public function test_billing_cannot_view_users_without_permission(): void
    {
        // billing no tiene users.view.
        $this->actingAs($this->userWithRole(RoleName::Billing))
            ->get('/admin/usuarios')
            ->assertForbidden();
    }

    public function test_billing_can_view_clients(): void
    {
        // billing tiene clients.view.
        $this->actingAs($this->userWithRole(RoleName::Billing))
            ->get('/admin/clientes')
            ->assertOk();
    }

    public function test_support_cannot_view_audit(): void
    {
        // support no tiene audit.view.
        $this->actingAs($this->userWithRole(RoleName::Support))
            ->get('/admin/auditoria')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_everything(): void
    {
        $super = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($super)->get('/admin/usuarios')->assertOk();
        $this->actingAs($super)->get('/admin/auditoria')->assertOk();
        $this->actingAs($super)->get('/admin/roles')->assertOk();
    }

    public function test_staff_can_access_its_areas(): void
    {
        $staff = $this->userWithRole(RoleName::Staff);

        // Atención de tickets, alta de clientes, pedidos, dominios y facturas (ver).
        $this->actingAs($staff)->get('/admin/tickets')->assertOk();
        $this->actingAs($staff)->get('/admin/clientes')->assertOk();
        $this->actingAs($staff)->get('/admin/clientes/nuevo')->assertOk();
        $this->actingAs($staff)->get('/admin/servicios')->assertOk();
        $this->actingAs($staff)->get('/admin/servicios/nuevo')->assertOk();
        $this->actingAs($staff)->get('/admin/dominios')->assertOk();
        $this->actingAs($staff)->get('/admin/facturas')->assertOk();
    }

    public function test_staff_cannot_access_admin_only_areas(): void
    {
        $staff = $this->userWithRole(RoleName::Staff);

        // Sin usuarios, roles, auditoría ni integraciones.
        $this->actingAs($staff)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($staff)->get('/admin/roles')->assertForbidden();
        $this->actingAs($staff)->get('/admin/auditoria')->assertForbidden();
        $this->actingAs($staff)->get('/admin/integraciones')->assertForbidden();
    }

    public function test_staff_has_expected_permissions(): void
    {
        $staff = $this->userWithRole(RoleName::Staff);

        // Permisos que SÍ debe tener.
        foreach ([
            'tickets.view', 'tickets.manage', 'tickets.reply',
            'clients.view', 'clients.manage',
            'services.view', 'services.manage',
            'domains.view', 'domains.manage',
            'addons.manage', 'kb.view', 'invoices.view',
        ] as $permission) {
            $this->assertTrue($staff->can($permission), "Staff debería tener {$permission}");
        }

        // Permisos que NO debe tener.
        foreach ([
            'users.view', 'roles.manage', 'audit.view', 'integrations.manage',
            'invoices.manage', 'payments.record', 'kb.manage', 'plans.manage',
        ] as $permission) {
            $this->assertFalse($staff->can($permission), "Staff NO debería tener {$permission}");
        }
    }
}
