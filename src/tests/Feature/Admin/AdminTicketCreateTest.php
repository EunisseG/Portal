<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Jobs\SendTicketNotification;
use App\Domain\Tickets\Models\Ticket;
use App\Livewire\Admin\Tickets\TicketCreate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTicketCreateTest extends TestCase
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

    public function test_admin_can_open_a_ticket_for_a_single_client(): void
    {
        Queue::fake();
        $client = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(TicketCreate::class)
            ->call('toggleClient', $client->id)
            ->set('subject', 'Aviso importante')
            ->set('department', TicketDepartment::Support->value)
            ->set('priority', TicketPriority::Normal->value)
            ->set('body', 'Contenido del aviso.')
            ->call('save')
            ->assertRedirect(route('admin.tickets.index'));

        $ticket = Ticket::where('client_id', $client->id)->firstOrFail();
        $this->assertSame('Aviso importante', $ticket->subject);
        // Abierto por staff => estado "respondido" y primer mensaje marcado staff.
        $this->assertSame(TicketStatus::Answered, $ticket->status);
        $this->assertTrue($ticket->messages()->where('is_staff', true)->exists());

        Queue::assertPushed(SendTicketNotification::class);
    }

    public function test_admin_can_open_a_ticket_for_multiple_clients(): void
    {
        Queue::fake();
        $a = Client::factory()->create();
        $b = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(TicketCreate::class)
            ->call('toggleClient', $a->id)
            ->call('toggleClient', $b->id)
            ->set('subject', 'Mantenimiento programado')
            ->set('body', 'Detalles del mantenimiento.')
            ->call('save');

        // Un ticket individual por cliente.
        $this->assertSame(1, Ticket::where('client_id', $a->id)->count());
        $this->assertSame(1, Ticket::where('client_id', $b->id)->count());
        $this->assertSame(2, Ticket::count());
    }

    public function test_at_least_one_client_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TicketCreate::class)
            ->set('subject', 'Sin destinatarios')
            ->set('body', 'x')
            ->call('save')
            ->assertHasErrors('clientIds');
    }

    public function test_each_client_sees_only_their_own_ticket(): void
    {
        Queue::fake();
        $me = User::factory()->create();
        $me->assignRole(RoleName::Client->value);
        $myClient = Client::factory()->create(['user_id' => $me->id]);

        $otherClient = Client::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(TicketCreate::class)
            ->call('toggleClient', $myClient->id)
            ->call('toggleClient', $otherClient->id)
            ->set('subject', 'MENSAJE MASIVO')
            ->set('body', 'Para ambos.')
            ->call('save');

        // El cliente solo ve su propio ticket en el listado.
        $this->actingAs($me->refresh())
            ->get('/client/tickets')
            ->assertOk()
            ->assertSee('MENSAJE MASIVO');

        $this->assertSame(1, Ticket::where('client_id', $myClient->id)->count());
    }

    public function test_billing_role_cannot_open_tickets(): void
    {
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        $this->actingAs($billing)
            ->get(route('admin.tickets.create'))
            ->assertForbidden();
    }

    public function test_preselecting_client_prefills_recipient_and_greeting(): void
    {
        $user = User::factory()->create(['name' => 'Ana Cliente']);
        $user->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($this->admin())
            ->withQueryParams(['client' => $client->id])
            ->test(TicketCreate::class)
            ->assertSet('clientIds', [$client->id])
            ->assertSet('body', "Hola Ana Cliente,\n\n");
    }

    public function test_selecting_service_autofills_subject_and_body(): void
    {
        $user = User::factory()->create(['name' => 'Beto Cliente']);
        $user->assignRole(RoleName::Client->value);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $service = \App\Domain\Services\Models\Service::factory()->create([
            'client_id' => $client->id,
            'name' => 'Hosting Empresarial',
        ]);

        Livewire::actingAs($this->admin())
            ->withQueryParams(['client' => $client->id])
            ->test(TicketCreate::class)
            ->set('serviceId', $service->id)
            ->assertSet('subject', 'Sobre tu servicio: Hosting Empresarial')
            ->assertSet('body', "Hola Beto Cliente,\n\nEn relación con tu servicio \"Hosting Empresarial\": ");
    }

    public function test_tickets_status_kpis_and_chart_have_distribution_data(): void
    {
        Ticket::factory()->create(['status' => TicketStatus::Open->value]);
        Ticket::factory()->create(['status' => TicketStatus::Open->value]);
        Ticket::factory()->create(['status' => TicketStatus::Pending->value]);
        Ticket::factory()->create(['status' => TicketStatus::Answered->value]);
        Ticket::factory()->create(['status' => TicketStatus::Closed->value]);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Tickets\TicketIndex::class)
            ->assertSee('Distribución de tickets por estado')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['open'] === 2
                && $kpis['pending'] === 1
                && $kpis['answered'] === 1
                && $kpis['resolved'] === 0
                && $kpis['closed'] === 1
                && $kpis['total'] === 5)
            // chart['data'] = [abierto, en espera, respondido, resuelto, cerrado]
            ->assertViewHas('chart', fn ($chart) => $chart['data'] === [2, 1, 1, 0, 1]
                && count($chart['labels']) === 5);
    }
}
