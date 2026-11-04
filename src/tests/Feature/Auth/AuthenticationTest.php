<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect();
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ]);

        $this->assertGuest();
    }

    public function test_blocked_users_are_logged_out(): void
    {
        $user = User::factory()->blocked()->create();
        $user->assignRole(RoleName::Client->value);

        // Autenticado manualmente, al visitar una ruta protegida el middleware
        // debe cerrar la sesión y redirigir a login.
        $this->actingAs($user)
            ->get('/client')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
