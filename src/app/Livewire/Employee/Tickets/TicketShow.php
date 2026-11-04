<?php

namespace App\Livewire\Employee\Tickets;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detalle de un ticket interno del empleado, con hilo de mensajes y respuesta.
 */
class TicketShow extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $body = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    /** Respuestas de la encuesta por pregunta: [question_id => valor]. */
    public array $surveyAnswers = [];

    public function mount(Ticket $ticket): void
    {
        // Solo tickets internos y solo los del propio empleado.
        abort_unless($ticket->isInternal(), 404);
        Gate::authorize('view', $ticket);
        $this->ticket = $ticket;
    }

    public function submitSurvey(TicketService $tickets): void
    {
        Gate::authorize('view', $this->ticket);

        $survey = $this->ticket->survey;

        // Solo se puede responder si existe la encuesta y aún no fue respondida.
        abort_if($survey === null || $survey->responded_at !== null, 403);

        if (! $this->validateSurvey()) {
            return;
        }

        $tickets->answerSurvey($survey, $this->surveyAnswers);

        $this->ticket->refresh();
        session()->flash('status', 'Gracias por tu respuesta.');
    }

    /**
     * Valida las respuestas contra las preguntas activas del formulario.
     * Devuelve false (y marca errores) si alguna respuesta no es válida.
     */
    private function validateSurvey(): bool
    {
        $this->resetErrorBag();

        $form = \App\Domain\Tickets\Models\SurveyForm::forType(
            $this->ticket->type ?? \App\Domain\Tickets\Enums\TicketType::Internal
        );

        foreach ($form->activeQuestions()->get() as $q) {
            $key = 'surveyAnswers.'.$q->id;
            $value = $this->surveyAnswers[$q->id] ?? null;

            if ($q->required && ($value === null || $value === '')) {
                $this->addError($key, 'Esta pregunta es obligatoria.');

                continue;
            }

            if ($value !== null && $value !== '' && $q->type === \App\Domain\Tickets\Enums\SurveyQuestionType::Scale
                && ! in_array((int) $value, [1, 2, 3, 4, 5], true)) {
                $this->addError($key, 'Selecciona un valor del 1 al 5.');
            }
        }

        return $this->getErrorBag()->isEmpty();
    }

    public function reply(TicketService $tickets): void
    {
        Gate::authorize('reply', $this->ticket);

        $data = $this->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        // El empleado responde como autor no-staff.
        $tickets->reply($this->ticket, auth()->user(), $data['body'], false, $this->attachments);

        $this->reset('body', 'attachments');
        $this->ticket->refresh();
        session()->flash('status', 'Respuesta enviada.');
    }

    public function render(): View
    {
        $this->ticket->load(['messages.author', 'messages.attachments', 'internalArea', 'survey']);

        $surveyForm = \App\Domain\Tickets\Models\SurveyForm::forType(
            $this->ticket->type ?? \App\Domain\Tickets\Enums\TicketType::Internal
        )->load('activeQuestions');

        return view('livewire.employee.tickets.ticket-show', [
            'surveyForm' => $surveyForm,
        ])->layout('layouts.employee');
    }
}
