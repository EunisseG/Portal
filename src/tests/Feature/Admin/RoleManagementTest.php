<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Livewire\Admin\Roles\RoleIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_admin_can_create_a_custom_role_with_permissions(): void
    {
        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('newRole')
            ->set('name', 'soporte_nivel2')
            ->set('selectedPermissions', ['tickets.view', 'tickets.reply'])
            ->call('save');

        $role = Role::where('name', 'soporte_nivel2')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('tickets.view'));
        $this->assertTrue($role->hasPermissionTo('tickets.reply'));
        $this->assertFalse($role->hasPermissionTo('invoices.manage'));
    }

    public function test_role_name_must_be_unique(): void
    {
        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('newRole')
            ->set('name', 'admin') // ya existe
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_role_name_format_is_validated(): void
    {
        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('newRole')
            ->set('name', 'Rol Con Espacios')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_admin_can_edit_a_custom_role(): void
    {
        $role = Role::findOrCreate('temporal', 'web');
        $role->syncPermissions(['tickets.view']);

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('edit', $role->id)
            ->set('selectedPermissions', ['tickets.view', 'clients.view'])
            ->call('save');

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('clients.view'));
    }

    public function test_admin_can_delete_a_custom_role(): void
    {
        $role = Role::findOrCreate('desechable', 'web');

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('delete', $role->id);

        $this->assertDatabaseMissing('roles', ['name' => 'desechable']);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $adminRole = Role::where('name', RoleName::Admin->value)->firstOrFail();

        Livewire::actingAs($this->admin())
            ->test(RoleIndex::class)
            ->call('delete', $adminRole->id)
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => RoleName::Admin->value]);
    }

    public function test_support_role_cannot_manage_roles(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
