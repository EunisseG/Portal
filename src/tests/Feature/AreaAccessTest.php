<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaAccessTest extends TestCase
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

    public function test_client_can_access_client_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Client))
            ->get('/client')
            ->assertOk();
    }

    public function test_client_cannot_access_admin_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Client))
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_cannot_access_client_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get('/client')
            ->assertForbidden();
    }

    public function test_dashboard_redirects_client_to_client_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Client))
            ->get('/dashboard')
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_dashboard_redirects_admin_to_admin_area(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/client')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }
}
