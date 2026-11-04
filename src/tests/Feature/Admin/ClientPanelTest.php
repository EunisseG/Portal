<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPanelTest extends TestCase
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

    public function test_admin_can_view_client_readonly_panel(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('admin.clients.panel', $client))
            ->assertOk()
            ->assertSee('Panel de')
            ->assertSee($client->user->name)
            ->assertSee('solo lectura');
    }

    public function test_client_show_has_link_to_panel(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get(route('admin.clients.show', $client))
            ->assertOk()
            ->assertSee('Ir al panel de cliente');
    }

    public function test_user_without_clients_permission_cannot_view_panel(): void
    {
        // El rol client no tiene clients.view y además no es del área admin.
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Client))
            ->get(route('admin.clients.panel', $client))
            ->assertForbidden();
    }
}
