<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\Audit;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Activity\ClientLoginHistory;
use App\Livewire\Admin\Activity\StaffLoginHistory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginHistoryTest extends TestCase
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

    /** Crea un usuario con rol y un registro de login en la auditoría. */
    private function loginRecord(RoleName $role, string $name, string $email): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => $email]);
        $user->assignRole($role->value);

        if ($role === RoleName::Client) {
            Client::factory()->create(['user_id' => $user->id]);
        }

        Audit::create([
            'user_id' => $user->id,
            'action' => AuditAction::Login->value,
            'ip_address' => '10.0.0.1',
            'created_at' => now(),
        ]);

        return $user;
    }

    public function test_staff_history_shows_only_staff_logins(): void
    {
        $this->loginRecord(RoleName::Admin, 'Admin Staff', 'staff@wortev.test');
        $this->loginRecord(RoleName::Client, 'Cliente Uno', 'cliente@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(StaffLoginHistory::class)
            ->assertSee('staff@wortev.test')
            ->assertDontSee('cliente@wortev.test');
    }

    public function test_staff_history_includes_employee_logins(): void
    {
        // Los empleados internos (que levantan tickets) también son personal:
        // sus accesos deben verse en el historial de inicio de sesión.
        $this->loginRecord(RoleName::Employee, 'Empleado Interno', 'empleado@wortev.test');
        $this->loginRecord(RoleName::Client, 'Cliente Uno', 'cliente@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(StaffLoginHistory::class)
            ->assertSee('empleado@wortev.test')
            ->assertDontSee('cliente@wortev.test');
    }

    public function test_client_history_shows_only_client_logins(): void
    {
        $this->loginRecord(RoleName::Admin, 'Admin Staff', 'staff@wortev.test');
        $this->loginRecord(RoleName::Client, 'Cliente Uno', 'cliente@wortev.test');

        Livewire::actingAs($this->admin())
            ->test(ClientLoginHistory::class)
            ->assertSee('cliente@wortev.test')
            ->assertDontSee('staff@wortev.test');
    }

    public function test_client_cannot_access_login_histories(): void
    {
        $client = User::factory()->create();
        $client->assignRole(RoleName::Client->value);

        $this->actingAs($client)
            ->get(route('admin.activity.staff-logins'))
            ->assertForbidden();
    }
}
