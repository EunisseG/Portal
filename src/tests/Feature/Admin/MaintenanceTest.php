<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Content\Enums\MaintenanceStatus;
use App\Domain\Content\Models\Maintenance;
use App\Domain\Clients\Models\Client;
use App\Livewire\Admin\Content\MaintenanceIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceTest extends TestCase
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

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_admin_can_create_a_maintenance(): void
    {
        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('title', 'Migración de servidores')
            ->set('description', 'Ventana nocturna.')
            ->set('starts_at', '2026-06-01T02:00')
            ->set('ends_at', '2026-06-01T04:00')
            ->set('status', MaintenanceStatus::Scheduled->value)
            ->set('is_published', true)
            ->call('save');

        $this->assertDatabaseHas('maintenances', [
            'title' => 'Migración de servidores',
            'is_published' => true,
        ]);
    }

    public function test_end_must_be_after_or_equal_start(): void
    {
        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->set('title', 'Rango inválido')
            ->set('starts_at', '2026-06-01T04:00')
            ->set('ends_at', '2026-06-01T02:00')
            ->call('save')
            ->assertHasErrors('ends_at');
    }

    public function test_admin_can_delete_a_maintenance(): void
    {
        $m = Maintenance::create([
            'title' => 'Borrar',
            'starts_at' => now(),
            'status' => MaintenanceStatus::Scheduled->value,
        ]);

        Livewire::actingAs($this->admin())
            ->test(MaintenanceIndex::class)
            ->call('delete', $m->id);

        $this->assertDatabaseMissing('maintenances', ['id' => $m->id]);
    }

    public function test_billing_role_cannot_manage_maintenances(): void
    {
        // billing no tiene news.manage.
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        $this->actingAs($billing)
            ->get(route('admin.maintenances.index'))
            ->assertForbidden();
    }

    public function test_client_sees_only_published_maintenances(): void
    {
        Maintenance::create([
            'title' => 'MANTENIMIENTO PUBLICADO',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => true,
        ]);
        Maintenance::create([
            'title' => 'MANTENIMIENTO OCULTO',
            'starts_at' => now()->addDay(),
            'status' => MaintenanceStatus::Scheduled->value,
            'is_published' => false,
        ]);

        $this->actingAs($this->clientUser())
            ->get(route('client.maintenances.index'))
            ->assertOk()
            ->assertSee('MANTENIMIENTO PUBLICADO')
            ->assertDontSee('MANTENIMIENTO OCULTO');
    }
}
