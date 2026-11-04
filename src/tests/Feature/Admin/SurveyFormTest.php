<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Tickets\Enums\SurveyQuestionType;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SatisfactionSurvey;
use App\Domain\Tickets\Models\SurveyAnswer;
use App\Domain\Tickets\Models\SurveyForm;
use App\Domain\Reporting\Services\SatisfactionStats;
use App\Livewire\Admin\Support\SlaIndex;
use App\Livewire\Admin\System\SurveyFormIndex;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SlaPoliciesSeeder;
use Database\Seeders\SurveyFormsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SurveyFormsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    // ===== Editor de encuestas =====

    public function test_admin_can_edit_survey_title(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SurveyFormIndex::class)
            ->set('title', 'Encuesta renovada')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertSame('Encuesta renovada', SurveyForm::forType(TicketType::Client)->title);
    }

    public function test_admin_can_add_a_question(): void
    {
        $before = SurveyForm::forType(TicketType::Client)->questions()->count();

        Livewire::actingAs($this->admin())
            ->test(SurveyFormIndex::class)
            ->call('newQuestion')
            ->set('q_label', '¿Recomendarías nuestro servicio?')
            ->set('q_type', SurveyQuestionType::Boolean->value)
            ->call('saveQuestion')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, SurveyForm::forType(TicketType::Client)->questions()->count());
        $this->assertDatabaseHas('survey_questions', [
            'label' => '¿Recomendarías nuestro servicio?',
            'type' => SurveyQuestionType::Boolean->value,
        ]);
    }

    public function test_admin_can_delete_a_question(): void
    {
        $form = SurveyForm::forType(TicketType::Client);
        $question = $form->questions()->first();

        Livewire::actingAs($this->admin())
            ->test(SurveyFormIndex::class)
            ->call('deleteQuestion', $question->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('survey_questions', ['id' => $question->id]);
    }

    public function test_editing_the_internal_tab_affects_the_internal_form(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SurveyFormIndex::class)
            ->call('setTab', 'internal')
            ->set('title', 'Encuesta interna editada')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertSame('Encuesta interna editada', SurveyForm::forType(TicketType::Internal)->title);
        // La de clientes no cambió.
        $this->assertNotSame('Encuesta interna editada', SurveyForm::forType(TicketType::Client)->title);
    }

    public function test_non_admin_without_permission_cannot_edit_surveys(): void
    {
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value); // sin sla.manage

        Livewire::actingAs($billing)
            ->test(SurveyFormIndex::class)
            ->assertForbidden();
    }

    // ===== Desglose por pregunta =====

    public function test_question_breakdown_computes_scale_average(): void
    {
        $form = SurveyForm::forType(TicketType::Client);
        $scale = $form->questions()->where('type', SurveyQuestionType::Scale->value)->firstOrFail();

        // Dos encuestas respondidas con 4 y 2 en esa pregunta de escala.
        foreach ([4, 2] as $value) {
            $survey = SatisfactionSurvey::create([
                'ticket_id' => \App\Domain\Tickets\Models\Ticket::factory()->create()->id,
                'type' => TicketType::Client->value,
                'score' => $value,
                'responded_at' => now(),
            ]);
            SurveyAnswer::create([
                'satisfaction_survey_id' => $survey->id,
                'survey_question_id' => $scale->id,
                'value_number' => $value,
            ]);
        }

        $breakdown = app(SatisfactionStats::class)->questionBreakdown(TicketType::Client);
        $scaleRow = collect($breakdown)->firstWhere('label', $scale->label);

        $this->assertNotNull($scaleRow);
        $this->assertSame(3.0, $scaleRow['average']); // (4+2)/2
        $this->assertSame(2, $scaleRow['count']);
    }

    // ===== SLA en pestañas (Métricas / Políticas) =====

    public function test_sla_screen_shows_policies_tab_without_leaving(): void
    {
        $this->seed(SlaPoliciesSeeder::class);

        Livewire::actingAs($this->admin())
            ->test(SlaIndex::class)
            ->assertSet('tab', 'metricas')
            ->call('setTab', 'politicas')
            ->assertSet('tab', 'politicas')
            ->assertSee('Políticas');
    }
}
