<?php

namespace App\Livewire\Employee\Tickets;

use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de los tickets internos del propio empleado (es su panel de inicio),
 * con pestañas de activos y cerrados.
 */
class TicketIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'active' | 'closed'. */
    #[Url(as: 'tab')]
    public string $tab = 'active';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isEmployee(), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'closed' ? 'closed' : 'active';
        $this->resetPage();
    }

    public function render(): View
    {
        $activeStatuses = [
            TicketStatus::Open->value,
            TicketStatus::Pending->value,
            TicketStatus::Answered->value,
            TicketStatus::Resolved->value,
        ];

        $tickets = Ticket::query()
            ->where('type', TicketType::Internal->value)
            ->where('opened_by_user_id', auth()->id())
            ->with('internalArea')
            ->when($this->tab === 'closed',
                fn ($q) => $q->where('status', TicketStatus::Closed->value),
                fn ($q) => $q->whereIn('status', $activeStatuses),
            )
            ->orderByDesc('last_reply_at')
            ->paginate(10);

        return view('livewire.employee.tickets.ticket-index', [
            'tickets' => $tickets,
        ])->layout('layouts.employee');
    }
}
