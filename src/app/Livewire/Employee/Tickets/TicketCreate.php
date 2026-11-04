<?php

namespace App\Livewire\Employee\Tickets;

use App\Domain\Internal\Models\InternalArea;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta de un ticket interno por parte de un empleado. Elige el área destino,
 * asunto, prioridad y criticidad.
 */
class TicketCreate extends Component
{
    use WithFileUploads;

    public string $subject = '';
    public string $internal_area_id = '';
    public string $priority = TicketPriority::Normal->value;
    public string $criticality = TicketCriticality::Medium->value;
    public string $body = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isEmployee(), 403);

        // Preselecciona el área del propio empleado como destino por defecto.
        $this->internal_area_id = (string) (auth()->user()->internal_area_id ?? '');
    }

    public function save(TicketService $tickets): mixed
    {
        $data = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'internal_area_id' => ['required', Rule::exists('internal_areas', 'id')->where('is_active', true)],
            'priority' => ['required', new Enum(TicketPriority::class)],
            'criticality' => ['required', new Enum(TicketCriticality::class)],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $ticket = $tickets->createInternal(
            auth()->user(),
            (int) $data['internal_area_id'],
            [
                'subject' => $data['subject'],
                'priority' => $data['priority'],
                'criticality' => $data['criticality'],
            ],
            $data['body'],
            $this->attachments,
        );

        session()->flash('status', 'Ticket enviado. El área correspondiente te responderá.');

        return $this->redirect(route('employee.tickets.show', $ticket), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.employee.tickets.ticket-create', [
            'areas' => InternalArea::where('is_active', true)->orderBy('name')->get(),
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
        ])->layout('layouts.employee');
    }
}
