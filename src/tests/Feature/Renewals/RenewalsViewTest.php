<?php

namespace Tests\Feature\Renewals;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewalsViewTest extends TestCase
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

    public function test_admin_sees_upcoming_items_in_renewals_view(): void
    {
        $client = Client::factory()->create();
        Service::factory()->create([
            'client_id' => $client->id,
            'name' => 'Servicio Por Vencer',
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get('/admin/vencimientos')
            ->assertOk()
            ->assertSee('Vencimientos')
            ->assertSee('Servicio Por Vencer');
    }

    public function test_billing_role_can_view_renewals(): void
    {
        // billing tiene services.view en el seeder, así que puede ver el tablero.
        $this->actingAs($this->userWithRole(RoleName::Billing))
            ->get('/admin/vencimientos')
            ->assertOk();
    }

    public function test_client_cannot_view_renewals(): void
    {
        // El rol client no accede al área admin.
        $user = $this->userWithRole(RoleName::Client);
        Client::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user->refresh())
            ->get('/admin/vencimientos')
            ->assertForbidden();
    }
}
