<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_new_users_register_as_clients_with_profile(): void
    {
        $response = $this->post('/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'nuevo@wortev.test',
            'password' => 'password-seguro-123',
            'password_confirmation' => 'password-seguro-123',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'nuevo@wortev.test')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::Client->value));
        $this->assertFalse($user->isAdminArea());
        $this->assertNotNull($user->client, 'El registro debe crear el perfil Client.');
    }
}
