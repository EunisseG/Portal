<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\InitialUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creates_super_admin_from_config(): void
    {
        config()->set('superadmin.email', 'jefe@wortev.mx');
        config()->set('superadmin.password', 'SuperSecreta123');
        config()->set('superadmin.name', 'Jefe WORTEV');

        $this->seed(InitialUsersSeeder::class);

        $user = User::where('email', 'jefe@wortev.mx')->first();
        $this->assertNotNull($user);
        $this->assertSame('Jefe WORTEV', $user->name);
        $this->assertTrue($user->hasRole(RoleName::SuperAdmin->value));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_is_idempotent_and_keeps_existing_password(): void
    {
        config()->set('superadmin.email', 'jefe@wortev.mx');
        config()->set('superadmin.password', 'SuperSecreta123');

        $this->seed(InitialUsersSeeder::class);
        $first = User::where('email', 'jefe@wortev.mx')->firstOrFail();
        $originalHash = $first->password;

        // Segunda ejecución: no duplica ni cambia la contraseña.
        $this->seed(InitialUsersSeeder::class);

        $this->assertSame(1, User::where('email', 'jefe@wortev.mx')->count());
        $this->assertSame($originalHash, $first->fresh()->password);
    }

    public function test_skips_when_config_missing(): void
    {
        config()->set('superadmin.email', null);
        config()->set('superadmin.password', null);

        $this->seed(InitialUsersSeeder::class);

        $this->assertSame(0, User::count());
    }
}
