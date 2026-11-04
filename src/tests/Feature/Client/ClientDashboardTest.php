<?php

namespace Tests\Feature\Client;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientDashboardSummary;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_summary_counts_services_and_expiring_soon(): void
    {
        $client = Client::factory()->create();

        // Activo, vence en 10 días (próximo a vencer).
        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(10)->toDateString(),
        ]);
        // Activo, vence en 100 días (NO próximo a vencer).
        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(100)->toDateString(),
        ]);
        // Cancelado (no cuenta como activo).
        Service::factory()->create([
            'client_id' => $client->id,
            'status' => ServiceStatus::Cancelled->value,
            'expires_at' => now()->addDays(5)->toDateString(),
        ]);

        $summary = app(ClientDashboardSummary::class)->for($client);

        $this->assertSame(3, $summary['services']['total']);
        $this->assertSame(2, $summary['services']['active']);
        // Próximos a vencer (30d): el de 10 días y el cancelado de 5 días = 2.
        $this->assertSame(2, $summary['services']['expiring_soon']);

        // Placeholders en cero.
        $this->assertSame(0, $summary['invoices']['total']);
        $this->assertSame(0, $summary['tickets']['total']);

        // Lista de próximos a vencer: el de 10 días y el cancelado de 5 días, ordenados por fecha.
        $this->assertCount(2, $summary['upcoming_services']);
        $this->assertTrue(
            $summary['upcoming_services']->first()->expires_at
                ->lessThanOrEqualTo($summary['upcoming_services']->last()->expires_at)
        );
    }

    public function test_summary_lists_recent_invoices_ordered_by_issue_date(): void
    {
        $client = Client::factory()->create();

        \App\Domain\Billing\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'folio' => 'WV-OLD',
            'issued_at' => now()->subDays(20)->toDateString(),
        ]);
        \App\Domain\Billing\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'folio' => 'WV-NEW',
            'issued_at' => now()->subDay()->toDateString(),
        ]);

        $summary = app(ClientDashboardSummary::class)->for($client);

        $this->assertCount(2, $summary['recent_invoices']);
        // La más reciente primero.
        $this->assertSame('WV-NEW', $summary['recent_invoices']->first()->folio);
    }

    public function test_client_desktop_renders_with_profile_and_service_data(): void
    {
        $user = $this->clientUser();
        Service::factory()->create([
            'client_id' => $user->client->id,
            'name' => 'Hosting Cliente Pro',
            'status' => ServiceStatus::Active->value,
            'expires_at' => now()->addDays(12)->toDateString(),
        ]);
        \App\Domain\Billing\Models\Invoice::factory()->create([
            'client_id' => $user->client->id,
            'folio' => 'WV-CLI-1',
        ]);

        $this->actingAs($user)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Escritorio')
            ->assertSee($user->name)
            ->assertSee('#'.$user->client->id)
            ->assertSee('Servicios')
            ->assertSee('Facturas')
            ->assertSee('Tickets')
            ->assertSee('Próximos a vencer')
            ->assertSee('Facturas recientes')
            ->assertSee('Hosting Cliente Pro')
            ->assertSee('WV-CLI-1')
            ->assertSee('Tus servicios')
            ->assertSee('Tus facturas')
            ->assertSee('clientServicesChart');
    }
}
