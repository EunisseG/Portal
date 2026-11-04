<?php

namespace Tests\Feature\Tickets;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientDashboardSummary;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketAttachment;
use App\Domain\Tickets\Models\TicketMessage;
use App\Domain\Tickets\Services\TicketService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(TicketService::DISK);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_admin_can_list_tickets_but_billing_cannot(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->get('/admin/tickets')
            ->assertOk();

        // billing no tiene tickets.view.
        $this->actingAs($this->userWithRole(RoleName::Billing))
            ->get('/admin/tickets')
            ->assertForbidden();
    }

    public function test_attachment_download_is_protected(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        $ticket = Ticket::factory()->create(['client_id' => $me->client->id]);
        $message = TicketMessage::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $me->id]);
        $attachment = TicketAttachment::create([
            'ticket_message_id' => $message->id,
            'path' => 'tickets/'.$ticket->id.'/demo.png',
            'original_name' => 'demo.png',
            'mime_type' => 'image/png',
            'size' => 100,
        ]);
        Storage::disk(TicketService::DISK)->put($attachment->path, 'fake');

        // Dueño puede descargar.
        $this->actingAs($me)
            ->get(route('tickets.attachment', $attachment))
            ->assertOk();

        // Otro cliente no.
        $this->actingAs($other)
            ->get(route('tickets.attachment', $attachment))
            ->assertForbidden();
    }

    public function test_dashboard_summary_counts_tickets(): void
    {
        $client = Client::factory()->create();

        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Open->value]);
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Answered->value]);
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Pending->value]);
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Closed->value]);

        $summary = app(ClientDashboardSummary::class)->for($client);

        $this->assertSame(4, $summary['tickets']['total']);
        // open = open + answered = 2
        $this->assertSame(2, $summary['tickets']['open']);
        $this->assertSame(1, $summary['tickets']['pending']);
    }

    public function test_admin_ticket_tabs_separate_active_and_closed(): void
    {
        $client = Client::factory()->create();
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Open->value, 'subject' => 'TICKET ABIERTO']);
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Answered->value, 'subject' => 'TICKET RESPONDIDO']);
        Ticket::factory()->create(['client_id' => $client->id, 'status' => TicketStatus::Closed->value, 'subject' => 'TICKET CERRADO']);

        // Pestaña Activos: muestra abiertos/respondidos, NO cerrados.
        \Livewire\Livewire::actingAs($this->userWithRole(RoleName::Admin))
            ->test(\App\Livewire\Admin\Tickets\TicketIndex::class)
            ->assertSee('TICKET ABIERTO')
            ->assertSee('TICKET RESPONDIDO')
            ->assertDontSee('TICKET CERRADO')
            // Pestaña Cerrados: solo cerrados.
            ->call('setTab', 'closed')
            ->assertSee('TICKET CERRADO')
            ->assertDontSee('TICKET ABIERTO')
            ->assertDontSee('TICKET RESPONDIDO');
    }
}
