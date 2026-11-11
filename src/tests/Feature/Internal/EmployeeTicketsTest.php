<?php

namespace Tests\Feature\Internal;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Internal\Models\EmployeeLoginCode;
use App\Domain\Internal\Models\InternalArea;
use App\Domain\Internal\Services\EmployeeLoginService;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\Ticket;
use App\Livewire\Admin\Internal\EmployeeIndex;
use App\Livewire\Admin\Internal\InternalAreaIndex;
use App\Livewire\Admin\Tickets\InternalTicketIndex;
use App\Livewire\Admin\Tickets\TicketIndex;
use App\Livewire\Employee\Auth\EmployeeLogin;
use App\Livewire\Employee\Tickets\TicketCreate as EmployeeTicketCreate;
use App\Livewire\Employee\Tickets\TicketIndex as EmployeeTicketIndex;
use App\Domain\Tickets\Jobs\SendInternalTicketNotification;
use App\Models\User;
use Database\Seeders\InternalAreasSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(InternalAreasSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function employee(?InternalArea $area = null): User
    {
        $area ??= InternalArea::first();
        $user = User::factory()->create([
            'employee_number' => 'E-'.fake()->unique()->numberBetween(1000, 9999),
            'internal_area_id' => $area->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    // ===== Login por PIN =====

    public function test_requesting_a_code_for_a_valid_employee_issues_a_pin(): void
    {
        $emp = $this->employee();

        Livewire::test(EmployeeLogin::class)
            ->set('employee_number', $emp->employee_number)
            ->call('requestCode')
            ->assertSet('step', 'code')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('employee_login_codes', ['user_id' => $emp->id, 'used_at' => null]);
    }

    public function test_unknown_number_advances_without_issuing_a_code(): void
    {
        Livewire::test(EmployeeLogin::class)
            ->set('employee_number', 'NO-EXISTE')
            ->call('requestCode')
            ->assertSet('step', 'code')
            ->assertHasNoErrors();

        $this->assertSame(0, EmployeeLoginCode::count());
    }

    public function test_valid_pin_logs_the_employee_in(): void
    {
        $emp = $this->employee();
        $pin = app(EmployeeLoginService::class)->issueCode($emp);

        Livewire::test(EmployeeLogin::class)
            ->set('step', 'code')
            ->set('employeeId', $emp->id)
            ->set('pin', $pin)
            ->call('verify')
            ->assertRedirect(route('employee.dashboard'));

        $this->assertAuthenticatedAs($emp);
    }

    public function test_wrong_pin_is_rejected(): void
    {
        $emp = $this->employee();
        app(EmployeeLoginService::class)->issueCode($emp);

        Livewire::test(EmployeeLogin::class)
            ->set('step', 'code')
            ->set('employeeId', $emp->id)
            ->set('pin', '000000')
            ->call('verify')
            ->assertHasErrors('pin');

        $this->assertGuest();
    }

    public function test_pin_cannot_be_reused(): void
    {
        $emp = $this->employee();
        $service = app(EmployeeLoginService::class);
        $pin = $service->issueCode($emp);

        $this->assertTrue($service->verifyCode($emp, $pin));
        $this->assertFalse($service->verifyCode($emp, $pin));
    }

    // ===== Aislamiento de áreas =====

    public function test_employee_cannot_access_admin_or_client_areas(): void
    {
        $emp = $this->employee();

        $this->actingAs($emp)->get('/admin')->assertForbidden();
        $this->actingAs($emp)->get('/client')->assertForbidden();
    }

    public function test_non_employee_cannot_access_employee_portal(): void
    {
        $this->actingAs($this->admin())->get('/empleados')->assertForbidden();
    }

    public function test_dashboard_redirects_employee_to_employee_portal(): void
    {
        $this->actingAs($this->employee())
            ->get('/dashboard')
            ->assertRedirect(route('employee.dashboard'));
    }

    public function test_guest_is_redirected_from_employee_portal(): void
    {
        $this->get('/empleados')->assertRedirect(route('login'));
    }

    // ===== CRUD admin de áreas y empleados =====

    public function test_admin_can_create_an_internal_area(): void
    {
        Livewire::actingAs($this->admin())
            ->test(InternalAreaIndex::class)
            ->set('name', 'Legal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('internal_areas', ['name' => 'Legal']);
    }

    public function test_area_with_employees_cannot_be_deleted(): void
    {
        $area = InternalArea::first();
        $this->employee($area);

        Livewire::actingAs($this->admin())
            ->test(InternalAreaIndex::class)
            ->call('delete', $area->id);

        $this->assertDatabaseHas('internal_areas', ['id' => $area->id]);
    }

    public function test_admin_can_register_an_employee(): void
    {
        $area = InternalArea::first();

        Livewire::actingAs($this->admin())
            ->test(EmployeeIndex::class)
            ->call('newEmployee')
            ->set('name', 'Juan')
            ->set('email', 'juan.emp@wortev.test')
            ->set('employee_number', 'E-5001')
            ->set('internal_area_id', (string) $area->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('employee_number', 'E-5001')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleName::Employee->value));
        $this->assertSame($area->id, $user->internal_area_id);
    }

    public function test_employee_number_must_be_unique(): void
    {
        $existing = $this->employee();
        $area = InternalArea::first();

        Livewire::actingAs($this->admin())
            ->test(EmployeeIndex::class)
            ->call('newEmployee')
            ->set('name', 'Otro')
            ->set('email', 'otro.emp@wortev.test')
            ->set('employee_number', $existing->employee_number)
            ->set('internal_area_id', (string) $area->id)
            ->call('save')
            ->assertHasErrors('employee_number');
    }

    public function test_support_without_permission_cannot_manage_employees(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Support->value); // no tiene employees.manage

        Livewire::actingAs($user)
            ->test(EmployeeIndex::class)
            ->assertForbidden();
    }

    // ===== Tickets internos =====

    public function test_employee_can_open_an_internal_ticket(): void
    {
        $emp = $this->employee();
        $area = InternalArea::first();

        Livewire::actingAs($emp)
            ->test(EmployeeTicketCreate::class)
            ->set('subject', 'No tengo acceso al sistema X')
            ->set('internal_area_id', (string) $area->id)
            ->set('priority', 'normal')
            ->set('criticality', 'medium')
            ->set('body', 'Necesito acceso urgente.')
            ->call('save')
            ->assertHasNoErrors();

        $ticket = Ticket::where('type', TicketType::Internal->value)->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->client_id);
        $this->assertSame($emp->id, $ticket->opened_by_user_id);
        $this->assertSame($area->id, $ticket->internal_area_id);
        // Los tickets internos no generan encuesta de satisfacción.
        $this->assertDatabaseMissing('satisfaction_surveys', ['ticket_id' => $ticket->id]);
    }

    public function test_employee_only_sees_their_own_internal_tickets(): void
    {
        $emp = $this->employee();
        $other = $this->employee();
        $area = InternalArea::first();

        $mine = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'subject' => 'Mi ticket interno',
        ]);
        Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $other->id,
            'internal_area_id' => $area->id,
            'subject' => 'Ticket ajeno',
        ]);

        Livewire::actingAs($emp)
            ->test(EmployeeTicketIndex::class)
            ->assertSee('Mi ticket interno')
            ->assertDontSee('Ticket ajeno');
    }

    public function test_internal_tickets_do_not_appear_in_client_ticket_admin(): void
    {
        $emp = $this->employee();
        $area = InternalArea::first();

        Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'subject' => 'Interno invisible en clientes',
        ]);

        // El admin de tickets de CLIENTES no debe mostrarlo.
        Livewire::actingAs($this->admin())
            ->test(TicketIndex::class)
            ->assertDontSee('Interno invisible en clientes');

        // El admin de tickets INTERNOS sí.
        Livewire::actingAs($this->admin())
            ->test(InternalTicketIndex::class)
            ->assertSee('Interno invisible en clientes');
    }

    public function test_separate_permissions_gate_internal_ticket_admin(): void
    {
        // Billing no tiene internal_tickets.view ni tickets.view.
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        Livewire::actingAs($billing)
            ->test(InternalTicketIndex::class)
            ->assertForbidden();
    }

    // ===== Correos de notificación por área =====

    public function test_area_notify_emails_are_validated_and_saved(): void
    {
        Livewire::actingAs($this->admin())
            ->test(InternalAreaIndex::class)
            ->set('name', 'Mesa de ayuda')
            ->set('notify_emails', 'agente1@wortev.test, agente2@wortev.test')
            ->call('save')
            ->assertHasNoErrors();

        $area = InternalArea::where('name', 'Mesa de ayuda')->first();
        $this->assertNotNull($area);
        $this->assertSame(['agente1@wortev.test', 'agente2@wortev.test'], $area->notifyEmailList());
    }

    public function test_area_rejects_invalid_notify_email(): void
    {
        Livewire::actingAs($this->admin())
            ->test(InternalAreaIndex::class)
            ->set('name', 'Área mala')
            ->set('notify_emails', 'bueno@wortev.test, esto-no-es-correo')
            ->call('save')
            ->assertHasErrors('notify_emails');

        $this->assertDatabaseMissing('internal_areas', ['name' => 'Área mala']);
    }

    public function test_creating_internal_ticket_queues_notification_to_area_agents(): void
    {
        Queue::fake();

        $area = InternalArea::first();
        $area->update(['notify_emails' => 'agente@wortev.test']);
        $emp = $this->employee($area);

        Livewire::actingAs($emp)
            ->test(EmployeeTicketCreate::class)
            ->set('subject', 'Necesito ayuda')
            ->set('internal_area_id', (string) $area->id)
            ->set('priority', 'normal')
            ->set('criticality', 'medium')
            ->set('body', 'Describo mi problema.')
            ->call('save')
            ->assertHasNoErrors();

        Queue::assertPushed(SendInternalTicketNotification::class, 1);
    }

    public function test_notification_job_emails_each_area_agent(): void
    {
        $area = InternalArea::first();
        $area->update(['notify_emails' => 'a@wortev.test, b@wortev.test']);
        $emp = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
        ]);

        $mail = \Mockery::mock(\App\Integrations\Contracts\MailGateway::class);
        // Un correo por cada agente del área (2).
        $mail->shouldReceive('send')->twice()->andReturn(\App\Integrations\Contracts\GatewayResult::ok());

        (new SendInternalTicketNotification($ticket->id))->handle($mail);
    }

    public function test_notification_job_does_nothing_without_area_emails(): void
    {
        $area = InternalArea::first();
        $area->update(['notify_emails' => null]);
        $emp = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
        ]);

        $mail = \Mockery::mock(\App\Integrations\Contracts\MailGateway::class);
        $mail->shouldNotReceive('send');

        (new SendInternalTicketNotification($ticket->id))->handle($mail);
    }

    // ===== SLA separado por tipo =====

    public function test_sla_resolver_uses_internal_targets_for_internal_tickets(): void
    {
        $this->seed(\Database\Seeders\SlaPoliciesSeeder::class);

        $area = InternalArea::first();
        $emp = $this->employee($area);

        $internal = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'criticality' => \App\Domain\Tickets\Enums\TicketCriticality::Critical->value,
        ]);

        $resolver = app(\App\Domain\Tickets\Services\SlaResolver::class);
        $target = $resolver->forTicket($internal);

        // Objetivo interno crítico del seeder: 120 / 480 (distinto del de cliente 60/240).
        $this->assertSame(120, $target['first_response_minutes']);
        $this->assertSame(480, $target['resolution_minutes']);
        $this->assertSame('global', $target['source']);
    }

    public function test_sla_metrics_screen_filters_by_ticket_type(): void
    {
        $this->seed(\Database\Seeders\SlaPoliciesSeeder::class);

        $area = InternalArea::first();
        $emp = $this->employee($area);
        $client = \App\Domain\Clients\Models\Client::factory()->create();

        // Un ticket de cliente y uno interno, ambos en el rango por defecto.
        Ticket::factory()->create([
            'type' => TicketType::Client->value,
            'client_id' => $client->id,
            'subject' => 'Ticket cliente SLA',
        ]);
        Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'subject' => 'Ticket interno SLA',
        ]);

        // Pestaña internos: solo cuenta el interno.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Support\SlaIndex::class)
            ->set('ticketType', 'internal')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['total'] === 1);

        // Pestaña clientes: solo cuenta el de cliente.
        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\Support\SlaIndex::class)
            ->set('ticketType', 'client')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['total'] === 1);
    }

    // ===== Encuesta de satisfacción interna =====

    public function test_resolving_internal_ticket_generates_survey_for_employee(): void
    {
        $area = InternalArea::first();
        $emp = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Open->value,
        ]);

        app(\App\Domain\Tickets\Services\TicketService::class)
            ->changeStatus($ticket, \App\Domain\Tickets\Enums\TicketStatus::Resolved);

        $this->assertDatabaseHas('satisfaction_surveys', [
            'ticket_id' => $ticket->id,
            'type' => TicketType::Internal->value,
            'user_id' => $emp->id,
            'client_id' => null,
        ]);
    }

    public function test_employee_can_answer_their_internal_survey(): void
    {
        $this->seed(\Database\Seeders\SurveyFormsSeeder::class);

        $area = InternalArea::first();
        $emp = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Resolved->value,
        ]);
        \App\Domain\Tickets\Models\SatisfactionSurvey::create([
            'ticket_id' => $ticket->id,
            'type' => TicketType::Internal->value,
            'user_id' => $emp->id,
        ]);

        $qid = \App\Domain\Tickets\Models\SurveyForm::forType(TicketType::Internal)
            ->activeQuestions()
            ->where('type', \App\Domain\Tickets\Enums\SurveyQuestionType::Scale->value)
            ->firstOrFail()->id;

        Livewire::actingAs($emp)
            ->test(\App\Livewire\Employee\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->set('surveyAnswers.'.$qid, 5)
            ->call('submitSurvey')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('satisfaction_surveys', [
            'ticket_id' => $ticket->id,
            'score' => 5,
        ]);
        $this->assertDatabaseHas('survey_answers', [
            'survey_question_id' => $qid,
            'value_number' => 5,
        ]);
    }

    public function test_employee_can_close_their_internal_ticket_from_detail(): void
    {
        $area = InternalArea::first();
        $emp = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
            'status' => \App\Domain\Tickets\Enums\TicketStatus::Open->value,
            'subject' => 'Acceso a VPN',
        ]);

        Livewire::actingAs($emp)
            ->test(\App\Livewire\Employee\Tickets\TicketShow::class, ['ticket' => $ticket])
            ->assertSee('Acceso a VPN')
            ->assertSee('Historial de conversación')
            ->assertSee('Cerrar ticket')
            ->call('close')
            ->assertSee('Este ticket está cerrado.');

        $this->assertSame(\App\Domain\Tickets\Enums\TicketStatus::Closed, $ticket->fresh()->status);
        // El cierre genera la encuesta interna del empleado.
        $this->assertDatabaseHas('satisfaction_surveys', ['ticket_id' => $ticket->id, 'user_id' => $emp->id]);
    }

    public function test_employee_cannot_close_another_employees_ticket(): void
    {
        $area = InternalArea::first();
        $emp = $this->employee($area);
        $other = $this->employee($area);

        $ticket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $other->id,
            'internal_area_id' => $area->id,
        ]);

        $this->assertFalse($emp->can('close', $ticket));
    }

    public function test_satisfaction_stats_separate_client_and_internal(): void
    {
        $area = InternalArea::first();
        $emp = $this->employee($area);
        $client = \App\Domain\Clients\Models\Client::factory()->create();

        $clientTicket = Ticket::factory()->create(['type' => TicketType::Client->value, 'client_id' => $client->id]);
        \App\Domain\Tickets\Models\SatisfactionSurvey::create([
            'ticket_id' => $clientTicket->id,
            'type' => TicketType::Client->value,
            'client_id' => $client->id,
            'score' => 4,
            'responded_at' => now(),
        ]);

        $internalTicket = Ticket::factory()->create([
            'type' => TicketType::Internal->value,
            'client_id' => null,
            'opened_by_user_id' => $emp->id,
            'internal_area_id' => $area->id,
        ]);
        \App\Domain\Tickets\Models\SatisfactionSurvey::create([
            'ticket_id' => $internalTicket->id,
            'type' => TicketType::Internal->value,
            'user_id' => $emp->id,
            'score' => 2,
            'responded_at' => now(),
        ]);

        $stats = app(\App\Domain\Reporting\Services\SatisfactionStats::class);

        $this->assertSame(4.0, $stats->average(TicketType::Client));
        $this->assertSame(2.0, $stats->average(TicketType::Internal));
        $this->assertSame(1, $stats->answered(TicketType::Client));
        $this->assertSame(1, $stats->answered(TicketType::Internal));
    }
}
