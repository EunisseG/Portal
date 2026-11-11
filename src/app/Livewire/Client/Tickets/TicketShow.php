<?php

namespace App\Livewire\Client\Tickets;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

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
            $this->ticket->type ?? \App\Domain\Tickets\Enums\TicketType::Client
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
            'body' => ['required', 'string', new \App\Rules\RichTextBody],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $tickets->reply($this->ticket, auth()->user(), $data['body'], false, $this->attachments);

        $this->reset('body', 'attachments');
        $this->ticket->refresh();
        session()->flash('status', 'Respuesta enviada.');
    }

    /** Cierra el ticket (genera la encuesta de satisfacción). */
    public function close(TicketService $tickets): void
    {
        Gate::authorize('close', $this->ticket);

        $tickets->changeStatus($this->ticket, \App\Domain\Tickets\Enums\TicketStatus::Closed);

        $this->reset('body', 'attachments');
        $this->ticket->refresh();
        session()->flash('status', 'Ticket cerrado.');
    }

    public function render(): View
    {
        $this->ticket->load(['messages.author', 'messages.attachments', 'survey']);

        $surveyForm = \App\Domain\Tickets\Models\SurveyForm::forType(
            $this->ticket->type ?? \App\Domain\Tickets\Enums\TicketType::Client
        )->load('activeQuestions');

        return view('livewire.client.tickets.ticket-show', [
            'surveyForm' => $surveyForm,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
