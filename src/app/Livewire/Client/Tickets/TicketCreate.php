<?php

namespace App\Livewire\Client\Tickets;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Services\TicketService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\WithFileUploads;

class TicketCreate extends Component
{
    use WithFileUploads;

    public string $subject = '';
    public string $department = TicketDepartment::Support->value;
    public string $priority = TicketPriority::Normal->value;
    public string $criticality = TicketCriticality::Medium->value;
    public string $body = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->clientAccount(), 403);
        abort_unless(auth()->user()->canClient('client.tickets.create'), 403);
    }

    public function save(TicketService $tickets): mixed
    {
        $data = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'department' => ['required', new Enum(TicketDepartment::class)],
            'priority' => ['required', new Enum(TicketPriority::class)],
            'criticality' => ['required', new Enum(TicketCriticality::class)],
            'body' => ['required', 'string', 'max:5000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $ticket = $tickets->create(
            auth()->user()->clientAccount()->id,
            auth()->user(),
            [
                'subject' => $data['subject'],
                'department' => $data['department'],
                'priority' => $data['priority'],
                'criticality' => $data['criticality'],
            ],
            $data['body'],
            $this->attachments,
        );

        session()->flash('status', 'Ticket creado. Te responderemos pronto.');

        return $this->redirect(route('client.tickets.show', $ticket), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.client.tickets.ticket-create', [
            'departmentOptions' => TicketDepartment::options(),
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
