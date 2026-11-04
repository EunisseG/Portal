<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\Audit;
use App\Domain\Auth\Enums\RoleName;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_admin_can_create_a_user_with_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UserForm::class)
            ->set('name', 'Nuevo Soporte')
            ->set('email', 'soporte@wortev.test')
            ->set('password', 'password-123')
            ->set('password_confirmation', 'password-123')
            ->set('roles', [RoleName::Support->value])
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'soporte@wortev.test')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleName::Support->value));

        $this->assertDatabaseHas('audits', ['action' => AuditAction::UserCreated->value]);
    }

    public function test_admin_can_block_and_unblock_a_user(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Client->value);

        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->call('toggleBlock', $target->id);

        $this->assertTrue($target->fresh()->isBlocked());
        $this->assertDatabaseHas('audits', ['action' => AuditAction::UserBlocked->value]);

        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->call('toggleBlock', $target->id);

        $this->assertFalse($target->fresh()->isBlocked());
    }

    public function test_admin_cannot_block_themselves(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(UserIndex::class)
            ->call('toggleBlock', $admin->id)
            ->assertForbidden();

        $this->assertFalse($admin->fresh()->isBlocked());
    }

    public function test_super_admin_cannot_be_blocked(): void
    {
        $super = User::factory()->create();
        $super->assignRole(RoleName::SuperAdmin->value);

        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->call('toggleBlock', $super->id)
            ->assertForbidden();

        $this->assertFalse($super->fresh()->isBlocked());
    }

    public function test_email_must_be_unique_when_creating(): void
    {
        $existing = User::factory()->create(['email' => 'ocupado@wortev.test']);

        Livewire::actingAs($this->admin())
            ->test(UserForm::class)
            ->set('name', 'Otro')
            ->set('email', 'ocupado@wortev.test')
            ->set('password', 'password-123')
            ->set('password_confirmation', 'password-123')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_tabs_separate_internal_users_and_clients(): void
    {
        $staff = User::factory()->create(['name' => 'Equipo Interno']);
        $staff->assignRole(RoleName::Staff->value);

        $client = User::factory()->create(['name' => 'Cliente Externo']);
        $client->assignRole(RoleName::Client->value);

        // Pestaña "internos": ve al staff, no al cliente.
        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->assertSet('tab', 'internal')
            ->assertSee('Equipo Interno')
            ->assertDontSee('Cliente Externo');

        // Pestaña "clientes": ve al cliente, no al staff.
        Livewire::actingAs($this->admin())
            ->test(UserIndex::class)
            ->call('setTab', 'clients')
            ->assertSee('Cliente Externo')
            ->assertDontSee('Equipo Interno');
    }

    public function test_admin_can_grant_custom_permissions_in_addition_to_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(UserForm::class)
            ->set('name', 'Soporte Plus')
            ->set('email', 'soporteplus@wortev.test')
            ->set('password', 'password-123')
            ->set('password_confirmation', 'password-123')
            ->set('roles', [RoleName::Support->value])
            // Permiso extra que el rol support NO trae por defecto.
            ->set('permissions', ['invoices.view'])
            ->call('save')
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'soporteplus@wortev.test')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleName::Support->value));
        // Se guarda como permiso DIRECTO y la verificación can() lo respeta.
        $this->assertTrue($user->hasDirectPermission('invoices.view'));
        $this->assertTrue($user->can('invoices.view'));
    }

    public function test_role_inherited_permissions_are_not_stored_as_direct(): void
    {
        // support ya trae 'clients.view' por su rol; aunque se marque, no debe
        // guardarse como permiso directo (spatie es aditivo).
        Livewire::actingAs($this->admin())
            ->test(UserForm::class)
            ->set('name', 'Soporte Normal')
            ->set('email', 'soportenormal@wortev.test')
            ->set('password', 'password-123')
            ->set('password_confirmation', 'password-123')
            ->set('roles', [RoleName::Support->value])
            ->set('permissions', ['clients.view', 'invoices.view'])
            ->call('save');

        $user = User::where('email', 'soportenormal@wortev.test')->firstOrFail();
        // clients.view viene del rol => NO directo; invoices.view es extra => directo.
        $this->assertFalse($user->hasDirectPermission('clients.view'));
        $this->assertTrue($user->hasDirectPermission('invoices.view'));
        // Pero ambos resuelven true vía can().
        $this->assertTrue($user->can('clients.view'));
        $this->assertTrue($user->can('invoices.view'));
    }

    public function test_editing_user_preloads_direct_permissions(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Support->value);
        $target->givePermissionTo('invoices.view');

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->assertSet('permissions', ['invoices.view']);
    }

    public function test_admin_can_revoke_a_custom_permission_on_edit(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Support->value);
        $target->givePermissionTo('invoices.view');

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->set('permissions', [])
            ->call('save');

        $this->assertFalse($target->fresh()->hasDirectPermission('invoices.view'));
    }

    public function test_editing_a_client_user_enters_client_mode(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Client->value);

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->assertSet('clientMode', true)
            ->assertSet('roles', [RoleName::Client->value])
            // No se muestra el selector de otros roles ni permisos.
            ->assertDontSee('Acceso personalizado')
            ->assertSee('Las cuentas del módulo de Clientes');
    }

    public function test_creating_with_for_client_enters_client_mode(): void
    {
        Livewire::actingAs($this->admin())
            ->withQueryParams(['for' => 'client'])
            ->test(UserForm::class)
            ->assertSet('clientMode', true)
            ->assertSet('roles', [RoleName::Client->value])
            ->assertDontSee('Acceso personalizado');
    }

    public function test_client_mode_forces_client_role_even_if_tampered(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Client->value);

        // Aunque se intente inyectar otros roles/permisos, el servidor fuerza client.
        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->set('roles', [RoleName::Admin->value, RoleName::Client->value])
            ->set('permissions', ['invoices.view'])
            ->call('save');

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole(RoleName::Client->value));
        $this->assertFalse($fresh->hasRole(RoleName::Admin->value));
        $this->assertFalse($fresh->hasDirectPermission('invoices.view'));
    }

    public function test_internal_user_form_still_shows_roles_and_permissions(): void
    {
        $target = User::factory()->create();
        $target->assignRole(RoleName::Support->value);

        Livewire::actingAs($this->admin())
            ->test(UserForm::class, ['user' => $target])
            ->assertSet('clientMode', false)
            ->assertSee('Acceso personalizado');
    }
}
