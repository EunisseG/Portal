<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Models\FailedLoginAttempt;
use App\Livewire\Admin\Security\FailedLoginIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FailedLoginTest extends TestCase
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

    public function test_failed_login_creates_an_attempt_record(): void
    {
        $user = User::factory()->create(['email' => 'real@wortev.test']);

        $this->post('/login', [
            'email' => 'real@wortev.test',
            'password' => 'contrasena-incorrecta',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('failed_login_attempts', [
            'email' => 'real@wortev.test',
        ]);
    }

    public function test_admin_can_view_failed_login_attempts(): void
    {
        FailedLoginAttempt::create([
            'email' => 'atacante@ejemplo.com',
            'ip_address' => '203.0.113.9',
            'user_agent' => 'TestAgent',
            'attempted_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.security.failed-logins'))
            ->assertOk()
            ->assertSee('Intentos de inicio de sesión fallidos')
            ->assertSee('atacante@ejemplo.com');
    }

    public function test_search_filters_by_email(): void
    {
        FailedLoginAttempt::create(['email' => 'uno@ejemplo.com', 'attempted_at' => now()]);
        FailedLoginAttempt::create(['email' => 'dos@ejemplo.com', 'attempted_at' => now()]);

        Livewire::actingAs($this->admin())
            ->test(FailedLoginIndex::class)
            ->set('search', 'uno@')
            ->assertSee('uno@ejemplo.com')
            ->assertDontSee('dos@ejemplo.com');
    }

    public function test_client_cannot_view_failed_login_attempts(): void
    {
        $client = User::factory()->create();
        $client->assignRole(RoleName::Client->value);

        $this->actingAs($client)
            ->get(route('admin.security.failed-logins'))
            ->assertForbidden();
    }
}
