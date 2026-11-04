<?php

namespace Tests\Feature\Tickets;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Reporting\Services\SatisfactionStats;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Jobs\SendSurveyInvitation;
use App\Domain\Tickets\Models\SatisfactionSurvey;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\MailGateway;
use App\Livewire\Client\Tickets\TicketShow as ClientTicketShow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SatisfactionSurveyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SurveyFormsSeeder::class);
    }

    /** Id de la primera pregunta de escala del formulario de clientes. */
    private function clientScaleQuestionId(): int
    {
        return \App\Domain\Tickets\Models\SurveyForm::forType(\App\Domain\Tickets\Enums\TicketType::Client)
            ->activeQuestions()
            ->where('type', \App\Domain\Tickets\Enums\SurveyQuestionType::Scale->value)
            ->firstOrFail()->id;
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

    public function test_closing_a_ticket_creates_a_pending_survey(): void
    {
        Queue::fake();

        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create([
            'client_id' => $client->id,
            'status' => TicketStatus::Open->value,
        ]);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed);

        $this->assertDatabaseHas('satisfaction_surveys', [
            'ticket_id' => $ticket->id,
            'client_id' => $client->id,
            'responded_at' => null,
        ]);
    }

    public function test_closing_twice_does_not_duplicate_survey(): void
    {
        Queue::fake();

        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open->value]);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed);
        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed);

        $this->assertSame(1, SatisfactionSurvey::where('ticket_id', $ticket->id)->count());
    }

    public function test_closing_dispatches_survey_invitation_once(): void
    {
        Queue::fake();

        $ticket = Ticket::factory()->create(['status' => TicketStatus::Open->value]);

        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed);
        // Cerrar de nuevo no debe reenviar la invitación.
        app(TicketService::class)->changeStatus($ticket, TicketStatus::Closed);

        Queue::assertPushed(SendSurveyInvitation::class, 1);
        Queue::assertPushed(SendSurveyInvitation::class, fn ($job) => $job->ticketId === $ticket->id);
    }

    public function test_survey_invitation_job_emails_the_client_with_link(): void
    {
        $user = $this->clientUser();
        $ticket = Ticket::factory()->create([
            'client_id' => $user->client->id,
            'subject' => 'Mi problema',
        ]);

        $mock = Mockery::mock(MailGateway::class);
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $body) use ($ticket, $user) {
                return $to === $user->email
                    && str_contains($subject, $ticket->number)
                    && str_contains($body, route('client.tickets.show', $ticket));
            })
            ->andReturn(GatewayResult::ok());

        (new SendSurveyInvitation($ticket->id))->handle($mock);
    }

    public function test_client_can_answer_survey_on_closed_ticket(): void
    {
        $user = $this->clientUser();
        $ticket = Ticket::factory()->create([
            'client_id' => $user->client->id,
            'status' => TicketStatus::Closed->value,
        ]);
        SatisfactionSurvey::create([
            'ticket_id' => $ticket->id,
            'client_id' => $user->client->id,
        ]);

        $qid = $this->clientScaleQuestionId();

        Livewire::actingAs($user)
            ->test(ClientTicketShow::class, ['ticket' => $ticket])
            ->set('surveyAnswers.'.$qid, 4)
            ->call('submitSurvey');

        // score (promedio de escalas) queda en la cabecera por compatibilidad.
        $this->assertDatabaseHas('satisfaction_surveys', [
            'ticket_id' => $ticket->id,
            'score' => 4,
        ]);
        $this->assertDatabaseHas('survey_answers', [
            'survey_question_id' => $qid,
            'value_number' => 4,
        ]);
        $this->assertNotNull($ticket->survey->fresh()->responded_at);
    }

    public function test_required_question_blocks_empty_submission(): void
    {
        $user = $this->clientUser();
        $ticket = Ticket::factory()->create([
            'client_id' => $user->client->id,
            'status' => TicketStatus::Closed->value,
        ]);
        SatisfactionSurvey::create([
            'ticket_id' => $ticket->id,
            'client_id' => $user->client->id,
        ]);

        $qid = $this->clientScaleQuestionId();

        // No responde la pregunta obligatoria de escala -> error.
        Livewire::actingAs($user)
            ->test(ClientTicketShow::class, ['ticket' => $ticket])
            ->call('submitSurvey')
            ->assertHasErrors('surveyAnswers.'.$qid);

        $this->assertNull($ticket->survey->fresh()->responded_at);
    }

    public function test_stats_compute_distribution_and_average(): void
    {
        $client = Client::factory()->create();

        foreach ([5, 5, 3, 1] as $i => $score) {
            $ticket = Ticket::factory()->create(['client_id' => $client->id]);
            SatisfactionSurvey::create([
                'ticket_id' => $ticket->id,
                'client_id' => $client->id,
                'score' => $score,
                'responded_at' => now(),
            ]);
        }
        // Una pendiente (sin responder) no cuenta.
        $pendingTicket = Ticket::factory()->create(['client_id' => $client->id]);
        SatisfactionSurvey::create(['ticket_id' => $pendingTicket->id, 'client_id' => $client->id]);

        $stats = app(SatisfactionStats::class);

        // distribución 1..5 => [1, 0, 1, 0, 2]
        $this->assertSame([1, 0, 1, 0, 2], $stats->distribution()['data']);
        $this->assertSame(4, $stats->answered());
        $this->assertSame(1, $stats->pending());
        // promedio (5+5+3+1)/4 = 3.5
        $this->assertSame(3.5, $stats->average());
    }

    public function test_admin_can_view_satisfaction_dashboard(): void
    {
        $client = Client::factory()->create();
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        SatisfactionSurvey::create([
            'ticket_id' => $ticket->id,
            'client_id' => $client->id,
            'score' => 5,
            'comment' => 'Excelente servicio',
            'responded_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.satisfaction.index'))
            ->assertOk()
            ->assertSee('Promedio CSAT')
            ->assertSee('Excelente servicio');
    }
}
