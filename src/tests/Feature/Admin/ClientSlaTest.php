<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Jobs\SendSlaBreachNotification;
use App\Domain\Tickets\Models\ClientSlaPolicy;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\SlaResolver;
use App\Livewire\Admin\Clients\ClientShow;
use App\Livewire\Admin\Support\SlaIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SlaPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ClientSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SlaPoliciesSeeder::class);
        Queue::fake();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_client_override_wins_over_global(): void
    {
        $client = Client::factory()->create();
        ClientSlaPolicy::create([
            'client_id' => $client->id,
            'criticality' => TicketCriticality::Critical->value,
            'first_response_minutes' => 15,
            'resolution_minutes' => 90,
        ]);

        $target = app(SlaResolver::class)
            ->forClientCriticality($client->id, TicketCriticality::Critical);

        $this->assertSame('client', $target['source']);
        $this->assertSame(15, $target['first_response_minutes']);
        $this->assertSame(90, $target['resolution_minutes']);
    }

    public function test_falls_back_to_global_when_no_override(): void
    {
        $client = Client::factory()->create();

        // Sin override para Alta: usa el global del seeder (240 / 1440).
        $target = app(SlaResolver::class)
            ->forClientCriticality($client->id, TicketCriticality::High);

        $this->assertSame('global', $target['source']);
        $this->assertSame(240, $target['first_response_minutes']);
        $this->assertSame(1440, $target['resolution_minutes']);
    }

    public function test_resolver_classifies_met_warning_and_breached(): void
    {
        $resolver = app(SlaResolver::class);

        // Global crítico: 60 min respuesta / 240 min resolución.
        // Cumplido: respondido a los 30 min.
        $met = Ticket::factory()->create([
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => now()->subMinutes(120),
            'first_response_at' => now()->subMinutes(90), // 30 min <= 60
        ]);
        $this->assertSame('met', $resolver->firstResponseStatus($met));

        // Por vencer (warning): sin responder, consumido 50 de 60 (>= 75%).
        $warning = Ticket::factory()->create([
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => now()->subMinutes(50),
            'first_response_at' => null,
        ]);
        $this->assertSame('warning', $resolver->firstResponseStatus($warning));

        // Vencido: sin responder, consumido 120 de 60.
        $breached = Ticket::factory()->create([
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => now()->subMinutes(120),
            'first_response_at' => null,
        ]);
        $this->assertSame('breached', $resolver->firstResponseStatus($breached));
    }

    public function test_sla_metrics_use_client_override(): void
    {
        $client = Client::factory()->create();
        // Override agresivo: 10 min respuesta / 20 min resolución.
        ClientSlaPolicy::create([
            'client_id' => $client->id,
            'criticality' => TicketCriticality::Critical->value,
            'first_response_minutes' => 10,
            'resolution_minutes' => 20,
        ]);

        // Ticket resuelto en 30 min: cumpliría el global (240) pero NO el override (20).
        Ticket::factory()->create([
            'client_id' => $client->id,
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => Carbon::parse('2026-06-01 10:00:00'),
            'first_response_at' => Carbon::parse('2026-06-01 10:30:00'),
            'resolved_at' => Carbon::parse('2026-06-01 10:30:00'),
        ]);

        Livewire::actingAs($this->admin())
            ->test(SlaIndex::class)
            ->set('from', '2026-06-01')
            ->set('to', '2026-06-30')
            ->call('applyFilter')
            ->assertViewHas('kpis', function ($kpis) {
                // Con el override, el único ticket incumple respuesta y resolución.
                return $kpis['total'] === 1
                    && $kpis['res_compliance'] === 0
                    && $kpis['breached'] === 2;
            });
    }

    public function test_admin_can_save_and_clear_client_sla_override(): void
    {
        $client = Client::factory()->create();

        $component = Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->set('slaOverrides.critical.first', 15)
            ->set('slaOverrides.critical.resolution', 90)
            ->call('saveSlaOverride', 'critical')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_sla_policies', [
            'client_id' => $client->id,
            'criticality' => 'critical',
            'first_response_minutes' => 15,
            'resolution_minutes' => 90,
        ]);

        $component->call('clearSlaOverride', 'critical');

        $this->assertDatabaseMissing('client_sla_policies', [
            'client_id' => $client->id,
            'criticality' => 'critical',
        ]);
    }

    public function test_save_override_rejects_resolution_below_first_response(): void
    {
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ClientShow::class, ['client' => $client])
            ->set('slaOverrides.high.first', 100)
            ->set('slaOverrides.high.resolution', 50)
            ->call('saveSlaOverride', 'high')
            ->assertHasErrors('sla_high');

        $this->assertDatabaseMissing('client_sla_policies', [
            'client_id' => $client->id,
            'criticality' => 'high',
        ]);
    }

    public function test_sla_check_alerts_breaches_and_does_not_duplicate(): void
    {
        // Ticket activo vencido (sin responder, muy pasado del objetivo global).
        $breached = Ticket::factory()->create([
            'status' => TicketStatus::Open->value,
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => now()->subDays(2),
            'first_response_at' => null,
            'resolved_at' => null,
        ]);

        // Ticket en tiempo: no debe alertar.
        Ticket::factory()->create([
            'status' => TicketStatus::Open->value,
            'criticality' => TicketCriticality::Low->value,
            'created_at' => now()->subMinutes(5),
            'first_response_at' => null,
        ]);

        $this->artisan('sla:check')->assertExitCode(0);

        Queue::assertPushed(SendSlaBreachNotification::class, 1);
        $this->assertNotNull($breached->fresh()->sla_breach_alerted_at);
        $this->assertDatabaseHas('integration_logs', [
            'event' => 'sla.breach',
            'internal_reference' => $breached->number,
        ]);

        // Segunda corrida: no re-alerta el mismo incumplimiento.
        $this->artisan('sla:check')->assertExitCode(0);
        Queue::assertPushed(SendSlaBreachNotification::class, 1);
    }

    public function test_sla_check_clears_alert_mark_when_back_in_time(): void
    {
        // Ticket marcado como ya avisado, pero ahora en tiempo (objetivo amplio
        // con override y poco tiempo transcurrido).
        $client = Client::factory()->create();
        ClientSlaPolicy::create([
            'client_id' => $client->id,
            'criticality' => TicketCriticality::Low->value,
            'first_response_minutes' => 10000,
            'resolution_minutes' => 20000,
        ]);

        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'status' => TicketStatus::Open->value,
            'criticality' => TicketCriticality::Low->value,
            'created_at' => now()->subMinutes(5),
            'first_response_at' => null,
            'sla_breach_alerted_at' => now()->subDay(),
        ]);

        $this->artisan('sla:check')->assertExitCode(0);

        $this->assertNull($ticket->fresh()->sla_breach_alerted_at);
        Queue::assertNotPushed(SendSlaBreachNotification::class);
    }
}
