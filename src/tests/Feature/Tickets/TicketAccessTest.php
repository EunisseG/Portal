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

    // ===== Detalle del ticket (cliente) =====

    public function test_client_ticket_detail_shows_header_and_conversation_history(): void
    {
        $me = $this->clientUser();
        $ticket = Ticket::factory()->create([
            'client_id' => $me->client->id,
            'status' => TicketStatus::Open->value,
            'subject' => 'Revisar SMTP',
        ]);
        TicketMessage::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $me->id, 'body' => 'Primer mensaje']);
        TicketMessage::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $me->id, 'body' => 'Segundo mensaje']);

        \Livewire\Livewire::actingAs($me)
            ->test(\App\Livewire\Client\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->assertSee('#'.$ticket->number)
            ->assertSee('Revisar SMTP')
            ->assertSee('Cerrar ticket')
            ->assertSee('Historial de conversación (2)')
            // El más reciente aparece primero.
            ->assertSeeInOrder(['Segundo mensaje', 'Primer mensaje']);
    }

    public function test_client_reply_with_formatting_is_stored_sanitized_and_rendered(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $me = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $me->client->id, 'status' => TicketStatus::Open->value]);

        \Livewire\Livewire::actingAs($me)
            ->test(\App\Livewire\Client\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->set('body', '<p><strong>Importante</strong></p><script>alert(1)</script><p onclick="x()">fin</p>')
            ->call('reply')
            ->assertHasNoErrors()
            ->assertSeeHtml('<strong>Importante</strong>')
            ->assertDontSeeHtml('<script>alert(1)</script>');

        $stored = TicketMessage::where('ticket_id', $ticket->id)->latest('id')->value('body');
        $this->assertStringContainsString('<strong>Importante</strong>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
    }

    public function test_client_cannot_send_an_empty_formatted_reply(): void
    {
        $me = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $me->client->id, 'status' => TicketStatus::Open->value]);

        \Livewire\Livewire::actingAs($me)
            ->test(\App\Livewire\Client\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->set('body', '<p><br></p>')
            ->call('reply')
            ->assertHasErrors('body');
    }

    public function test_client_can_close_their_own_ticket(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $me = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $me->client->id, 'status' => TicketStatus::Open->value]);

        \Livewire\Livewire::actingAs($me)
            ->test(\App\Livewire\Client\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->call('close')
            ->assertHasNoErrors()
            ->assertSee('Este ticket está cerrado.')
            ->assertDontSee('Cerrar ticket');

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertDatabaseHas('satisfaction_surveys', ['ticket_id' => $ticket->id]);
    }

    public function test_client_cannot_close_someone_elses_or_an_already_closed_ticket(): void
    {
        $me = $this->clientUser();
        $other = $this->clientUser();

        $foreign = Ticket::factory()->create(['client_id' => $other->client->id, 'status' => TicketStatus::Open->value]);
        $closed = Ticket::factory()->create(['client_id' => $me->client->id, 'status' => TicketStatus::Closed->value]);

        $this->assertFalse($me->can('close', $foreign));
        $this->assertFalse($me->can('close', $closed));
        $this->assertTrue($other->can('close', $foreign));
    }
}
