<?php

namespace App\Livewire\Admin\Tickets;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detalle de un ticket INTERNO para el equipo de soporte: hilo, respuesta y
 * gestión de estado/prioridad/criticidad.
 */
class InternalTicketShow extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $body = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public string $status = '';
    public string $priority = '';
    public string $criticality = '';

    public function mount(Ticket $ticket): void
    {
        abort_unless($ticket->isInternal(), 404);
        Gate::authorize('view', $ticket);
        $this->ticket = $ticket;
        $this->status = $ticket->status->value;
        $this->priority = $ticket->priority->value;
        $this->criticality = $ticket->criticality?->value ?? TicketCriticality::Medium->value;
    }

    public function reply(TicketService $tickets): void
    {
        Gate::authorize('reply', $this->ticket);

        $data = $this->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        // Responde el staff interno (is_staff = true).
        $tickets->reply($this->ticket, auth()->user(), $data['body'], true, $this->attachments);

        $this->reset('body', 'attachments');
        $this->ticket->refresh();
        $this->status = $this->ticket->status->value;
        session()->flash('status', 'Respuesta enviada.');
    }

    public function updateStatus(TicketService $tickets): void
    {
        Gate::authorize('manage', $this->ticket);

        $this->validate(['status' => ['required', new Enum(TicketStatus::class)]]);
        $tickets->changeStatus($this->ticket, TicketStatus::from($this->status));
        $this->ticket->refresh();
        session()->flash('status', 'Estado actualizado.');
    }

    public function updatePriority(TicketService $tickets): void
    {
        Gate::authorize('manage', $this->ticket);

        $this->validate(['priority' => ['required', new Enum(TicketPriority::class)]]);
        $tickets->changePriority($this->ticket, TicketPriority::from($this->priority));
        $this->ticket->refresh();
        session()->flash('status', 'Prioridad actualizada.');
    }

    public function updateCriticality(TicketService $tickets): void
    {
        Gate::authorize('manage', $this->ticket);

        $this->validate(['criticality' => ['required', new Enum(TicketCriticality::class)]]);
        $tickets->changeCriticality($this->ticket, TicketCriticality::from($this->criticality));
        $this->ticket->refresh();
        session()->flash('status', 'Criticidad actualizada.');
    }

    public function render(): View
    {
        $this->ticket->load(['openedBy', 'internalArea', 'messages.author', 'messages.attachments']);

        return view('livewire.admin.tickets.internal-ticket-show', [
            'statusOptions' => TicketStatus::options(),
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
