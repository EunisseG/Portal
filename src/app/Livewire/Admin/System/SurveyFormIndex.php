<?php

namespace App\Livewire\Admin\System;

use App\Domain\Tickets\Enums\SurveyQuestionType;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SurveyForm;
use App\Domain\Tickets\Models\SurveyQuestion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Editor de las encuestas de satisfacción. Dos formularios independientes
 * (clientes / empleados), cada uno con su título, instrucciones y preguntas
 * configurables (agregar, editar, ordenar, activar o quitar).
 */
class SurveyFormIndex extends Component
{
    /** Pestaña activa: 'client' | 'internal'. */
    #[Url(as: 'tab')]
    public string $tab = 'client';

    // ----- Cabecera del formulario (título / instrucciones) -----
    public string $title = '';
    public string $instructions = '';

    // ----- Formulario de pregunta (crear / editar) -----
    public bool $showQuestionForm = false;
    public ?int $editingQuestionId = null;
    public string $q_label = '';
    public string $q_type = SurveyQuestionType::Scale->value;
    public bool $q_required = true;
    public bool $q_active = true;

    public function mount(): void
    {
        Gate::authorize('surveys.manage');
        $this->loadForm();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'internal' ? 'internal' : 'client';
        $this->cancelQuestion();
        $this->loadForm();
    }

    private function currentForm(): SurveyForm
    {
        return SurveyForm::forType(TicketType::from($this->tab));
    }

    private function loadForm(): void
    {
        $form = $this->currentForm();
        $this->title = (string) $form->title;
        $this->instructions = (string) $form->instructions;
    }

    /** Guarda el título e instrucciones del formulario del tipo activo. */
    public function saveForm(): void
    {
        Gate::authorize('surveys.manage');

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->currentForm()->update([
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?: null,
        ]);

        session()->flash('status', 'Encuesta actualizada.');
    }

    // ----- Preguntas -----

    public function newQuestion(): void
    {
        $this->reset('editingQuestionId', 'q_label', 'q_type', 'q_required', 'q_active');
        $this->q_type = SurveyQuestionType::Scale->value;
        $this->q_required = true;
        $this->q_active = true;
        $this->resetValidation();
        $this->showQuestionForm = true;
    }

    public function editQuestion(int $id): void
    {
        Gate::authorize('surveys.manage');

        $question = $this->questionForCurrentForm($id);
        $this->editingQuestionId = $question->id;
        $this->q_label = $question->label;
        $this->q_type = $question->type->value;
        $this->q_required = $question->required;
        $this->q_active = $question->is_active;
        $this->resetValidation();
        $this->showQuestionForm = true;
    }

    public function cancelQuestion(): void
    {
        $this->reset('showQuestionForm', 'editingQuestionId', 'q_label', 'q_type', 'q_required', 'q_active');
        $this->q_type = SurveyQuestionType::Scale->value;
        $this->q_required = true;
        $this->q_active = true;
        $this->resetValidation();
    }

    public function saveQuestion(): void
    {
        Gate::authorize('surveys.manage');

        $data = $this->validate([
            'q_label' => ['required', 'string', 'max:255'],
            'q_type' => ['required', new Enum(SurveyQuestionType::class)],
            'q_required' => ['boolean'],
            'q_active' => ['boolean'],
        ]);

        $form = $this->currentForm();

        if ($this->editingQuestionId) {
            $question = $this->questionForCurrentForm($this->editingQuestionId);
            $question->update([
                'label' => $data['q_label'],
                'type' => $data['q_type'],
                'required' => $data['q_required'],
                'is_active' => $data['q_active'],
            ]);
            session()->flash('status', 'Pregunta actualizada.');
        } else {
            $nextOrder = (int) $form->questions()->max('sort_order') + 1;
            $form->questions()->create([
                'label' => $data['q_label'],
                'type' => $data['q_type'],
                'required' => $data['q_required'],
                'is_active' => $data['q_active'],
                'sort_order' => $nextOrder,
            ]);
            session()->flash('status', 'Pregunta agregada.');
        }

        $this->cancelQuestion();
    }

    public function deleteQuestion(int $id): void
    {
        Gate::authorize('surveys.manage');

        $this->questionForCurrentForm($id)->delete();
        session()->flash('status', 'Pregunta eliminada.');
    }

    /** Sube o baja una pregunta en el orden. */
    public function move(int $id, string $direction): void
    {
        Gate::authorize('surveys.manage');

        $questions = $this->currentForm()->questions()->get();
        $index = $questions->search(fn ($q) => $q->id === $id);
        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $questions->count()) {
            return;
        }

        $a = $questions[$index];
        $b = $questions[$swapWith];
        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];
        $a->save();
        $b->save();
    }

    private function questionForCurrentForm(int $id): SurveyQuestion
    {
        return SurveyQuestion::where('survey_form_id', $this->currentForm()->id)->findOrFail($id);
    }

    public function render(): View
    {
        $form = $this->currentForm()->load('questions');

        return view('livewire.admin.system.survey-form-index', [
            'questions' => $form->questions,
            'typeOptions' => SurveyQuestionType::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
