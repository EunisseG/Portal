<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\SlaPolicy;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Livewire\Admin\Support\SlaIndex;
use App\Livewire\Admin\Support\SlaPolicyIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SlaPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SlaTest extends TestCase
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

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    public function test_sla_policies_are_seeded_for_each_criticality(): void
    {
        // 4 criticidades x 2 tipos (cliente/interno) = 8 políticas.
        $this->assertSame(8, SlaPolicy::count());
        foreach (TicketCriticality::cases() as $c) {
            $this->assertNotNull(SlaPolicy::forCriticality($c, \App\Domain\Tickets\Enums\TicketType::Client));
            $this->assertNotNull(SlaPolicy::forCriticality($c, \App\Domain\Tickets\Enums\TicketType::Internal));
        }
    }

    public function test_staff_reply_sets_first_response_at(): void
    {
        $client = Client::factory()->create();
        $author = $client->user;

        // Ticket abierto por el cliente: sin primera respuesta aún.
        $ticket = app(TicketService::class)->create(
            $client->id,
            $author,
            ['subject' => 'Ayuda', 'department' => 'support', 'priority' => 'normal', 'criticality' => 'high'],
            'Hola, tengo un problema.',
            [],
            isStaff: false,
        );
        $this->assertNull($ticket->fresh()->first_response_at);
        $this->assertSame(TicketCriticality::High, $ticket->fresh()->criticality);

        // Responde el staff => se marca la primera respuesta.
        app(TicketService::class)->reply($ticket, $this->staff(), 'Te ayudo.', true);
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_ticket_opened_by_staff_has_first_response_immediately(): void
    {
        $client = Client::factory()->create();
        $ticket = app(TicketService::class)->create(
            $client->id,
            $this->staff(),
            ['subject' => 'Aviso', 'department' => 'support', 'priority' => 'normal', 'criticality' => 'medium'],
            'Mensaje del staff.',
            [],
            isStaff: true,
        );

        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_resolving_sets_resolved_at_and_generates_survey(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open->value]);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Resolved);

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
        // La encuesta CSAT se genera también al Resolver (no solo al Cerrar).
        $this->assertDatabaseHas('satisfaction_surveys', ['ticket_id' => $ticket->id]);
    }

    public function test_reopening_clears_resolved_at(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open->value]);
        $service = app(TicketService::class);

        $service->changeStatus($ticket, TicketStatus::Resolved);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        // Reabrir a un estado activo limpia la marca de resolución.
        $service->changeStatus($ticket->fresh(), TicketStatus::Open);
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_admin_can_edit_an_sla_policy(): void
    {
        $policy = SlaPolicy::forCriticality(TicketCriticality::Critical);

        Livewire::actingAs($this->admin())
            ->test(SlaPolicyIndex::class)
            ->call('edit', $policy->id)
            ->set('first_response_minutes', 30)
            ->set('resolution_minutes', 120)
            ->call('save')
            ->assertHasNoErrors();

        $policy->refresh();
        $this->assertSame(30, $policy->first_response_minutes);
        $this->assertSame(120, $policy->resolution_minutes);
    }

    public function test_sla_policy_resolution_cannot_be_less_than_first_response(): void
    {
        $policy = SlaPolicy::forCriticality(TicketCriticality::Medium);

        Livewire::actingAs($this->admin())
            ->test(SlaPolicyIndex::class)
            ->call('edit', $policy->id)
            ->set('first_response_minutes', 100)
            ->set('resolution_minutes', 50)
            ->call('save')
            ->assertHasErrors('resolution_minutes');
    }

    public function test_sla_metrics_compute_compliance(): void
    {
        // Política crítica: 60 min respuesta / 240 min resolución (del seeder).
        // Ticket A: resuelto dentro de objetivo (cumple). Ticket B: fuera (vencido).
        $within = Ticket::factory()->create([
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => Carbon::parse('2026-06-01 10:00:00'),
            'first_response_at' => Carbon::parse('2026-06-01 10:30:00'),  // 30 min <= 60
            'resolved_at' => Carbon::parse('2026-06-01 12:00:00'),        // 120 min <= 240
        ]);
        $breached = Ticket::factory()->create([
            'criticality' => TicketCriticality::Critical->value,
            'created_at' => Carbon::parse('2026-06-02 10:00:00'),
            'first_response_at' => Carbon::parse('2026-06-02 12:00:00'),  // 120 min > 60
            'resolved_at' => Carbon::parse('2026-06-03 10:00:00'),        // 1440 min > 240
        ]);

        Livewire::actingAs($this->admin())
            ->test(SlaIndex::class)
            ->set('from', '2026-06-01')
            ->set('to', '2026-06-30')
            ->call('applyFilter')
            ->assertSee('SLA / Soporte')
            ->assertViewHas('kpis', function ($kpis) {
                // 2 tickets, uno cumple y otro vence => 50% cumplimiento resolución,
                // y 2 incumplimientos (1 de respuesta + 1 de resolución del vencido).
                return $kpis['total'] === 2
                    && $kpis['res_compliance'] === 50
                    && $kpis['breached'] === 2;
            });
    }

    public function test_support_without_sla_manage_still_sees_metrics(): void
    {
        // support tiene tickets.view y sla.manage (según seeder) => ve todo.
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        Livewire::actingAs($support)
            ->test(SlaIndex::class)
            ->assertOk();
    }
}
