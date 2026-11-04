<?php

namespace Tests\Feature\Tickets;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Livewire\Admin\Tickets\TicketShow as AdminTicketShow;
use App\Livewire\Client\Tickets\TicketCreate;
use App\Livewire\Client\Tickets\TicketShow as ClientTicketShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TicketFlowTest extends TestCase
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

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_client_can_create_a_ticket_with_attachment(): void
    {
        $user = $this->clientUser();

        Livewire::actingAs($user)
            ->test(TicketCreate::class)
            ->set('subject', 'No carga mi sitio')
            ->set('department', TicketDepartment::Support->value)
            ->set('priority', TicketPriority::High->value)
            ->set('body', 'Mi sitio muestra error 500 desde hoy.')
            ->set('attachments', [UploadedFile::fake()->create('captura.png', 50)])
            ->call('save')
            ->assertRedirect();

        $ticket = Ticket::where('subject', 'No carga mi sitio')->firstOrFail();
        $this->assertSame($user->client->id, $ticket->client_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertCount(1, $ticket->messages);
        $this->assertCount(1, $ticket->messages->first()->attachments);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::TicketCreated->value]);
    }

    public function test_staff_reply_sets_status_answered(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open->value]);

        Livewire::actingAs($this->admin())
            ->test(AdminTicketShow::class, ['ticket' => $ticket])
            ->set('body', 'Ya lo revisamos, quedó resuelto.')
            ->call('reply');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Answered, $ticket->status);
        $this->assertTrue($ticket->messages()->where('is_staff', true)->exists());
        $this->assertDatabaseHas('audits', ['action' => AuditAction::TicketReplied->value]);
    }

    public function test_admin_can_change_status_and_priority(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => TicketStatus::Open->value,
            'priority' => TicketPriority::Normal->value,
        ]);

        Livewire::actingAs($this->admin())
            ->test(AdminTicketShow::class, ['ticket' => $ticket])
            ->set('status', TicketStatus::Closed->value)
            ->call('updateStatus')
            ->set('priority', TicketPriority::Urgent->value)
            ->call('updatePriority');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertSame(TicketPriority::Urgent, $ticket->priority);
    }

    public function test_client_cannot_open_another_clients_ticket(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        $foreign = Ticket::factory()->create(['client_id' => $other->client->id]);

        Livewire::actingAs($me)
            ->test(ClientTicketShow::class, ['ticket' => $foreign])
            ->assertForbidden();
    }

    public function test_client_sees_only_their_tickets_in_list(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        Ticket::factory()->create(['client_id' => $me->client->id, 'subject' => 'MI TICKET PROPIO']);
        Ticket::factory()->create(['client_id' => $other->client->id, 'subject' => 'TICKET AJENO']);

        $this->actingAs($me)
            ->get('/client/tickets')
            ->assertOk()
            ->assertSee('MI TICKET PROPIO')
            ->assertDontSee('TICKET AJENO');
    }
}
