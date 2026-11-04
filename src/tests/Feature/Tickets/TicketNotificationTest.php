<?php

namespace Tests\Feature\Tickets;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketEvent;
use App\Domain\Tickets\Jobs\SendTicketNotification;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Integrations\Contracts\MailGateway;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
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

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_opening_a_ticket_dispatches_notification(): void
    {
        Queue::fake();
        $user = $this->clientUser();

        app(TicketService::class)->create(
            $user->client->id,
            $user,
            ['subject' => 'Prueba', 'department' => 'support', 'priority' => 'normal'],
            'Cuerpo del ticket',
        );

        Queue::assertPushed(SendTicketNotification::class, function ($job) {
            return $job->event === TicketEvent::Opened;
        });
    }

    public function test_client_reply_dispatches_client_replied_notification(): void
    {
        Queue::fake();
        $user = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $user->client->id]);

        app(TicketService::class)->reply($ticket, $user, 'Respuesta del cliente', false);

        Queue::assertPushed(SendTicketNotification::class, fn ($job) => $job->event === TicketEvent::ClientReplied);
    }

    public function test_staff_reply_dispatches_staff_replied_notification(): void
    {
        Queue::fake();
        $ticket = Ticket::factory()->create();

        app(TicketService::class)->reply($ticket, $this->staff(), 'Respuesta staff', true);

        Queue::assertPushed(SendTicketNotification::class, fn ($job) => $job->event === TicketEvent::StaffReplied);
        // El staff reply también dispara el aviso por WhatsApp al cliente.
        Queue::assertPushed(\App\Domain\Tickets\Jobs\SendTicketReplyWhatsApp::class);
    }

    public function test_client_reply_does_not_dispatch_whatsapp(): void
    {
        Queue::fake();
        $user = $this->clientUser();
        $ticket = Ticket::factory()->create(['client_id' => $user->client->id]);

        app(TicketService::class)->reply($ticket, $user, 'Respuesta cliente', false);

        Queue::assertNotPushed(\App\Domain\Tickets\Jobs\SendTicketReplyWhatsApp::class);
    }

    public function test_job_sends_mail_to_configured_address(): void
    {
        config()->set('tickets.notify_email', 'destino_infra@wortev.test');

        $ticket = Ticket::factory()->create(['subject' => 'Asunto de prueba']);

        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body) use ($ticket) {
                return $to === 'destino_infra@wortev.test'
                    && str_contains($subject, $ticket->number)
                    && str_contains($body, $ticket->subject);
            })
            ->andReturn(\App\Integrations\Contracts\GatewayResult::ok());

        (new SendTicketNotification($ticket->id, TicketEvent::Opened))->handle($mock);
    }
}
