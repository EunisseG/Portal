<?php

namespace App\Livewire\Admin\Tickets;

use App\Domain\Internal\Models\InternalArea;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bandeja de tickets INTERNOS (levantados por empleados) para el equipo de
 * soporte. Separada de los tickets de clientes.
 */
class InternalTicketIndex extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $tab = 'active';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'area')]
    public string $areaFilter = '';

    #[Url(as: 'priority')]
    public string $priorityFilter = '';

    public function mount(): void
    {
        abort_unless(Gate::allows('internal_tickets.view'), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'closed' ? 'closed' : 'active';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAreaFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
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
            ->with(['openedBy', 'internalArea'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhere('subject', 'like', "%{$this->search}%")
                        ->orWhereHas('openedBy', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->tab === 'closed',
                fn ($q) => $q->where('status', TicketStatus::Closed->value),
                fn ($q) => $q->whereIn('status', $activeStatuses),
            )
            ->when($this->areaFilter !== '', fn ($q) => $q->where('internal_area_id', (int) $this->areaFilter))
            ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter))
            ->orderByDesc('last_reply_at')
            ->paginate(10);

        $counts = Ticket::where('type', TicketType::Internal->value)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpis = [
            'open' => (int) ($counts[TicketStatus::Open->value] ?? 0),
            'pending' => (int) ($counts[TicketStatus::Pending->value] ?? 0),
            'answered' => (int) ($counts[TicketStatus::Answered->value] ?? 0),
            'resolved' => (int) ($counts[TicketStatus::Resolved->value] ?? 0),
            'closed' => (int) ($counts[TicketStatus::Closed->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];

        // Gráfica de distribución por estado (mismo formato que tickets de cliente).
        $chart = [
            'labels' => [
                TicketStatus::Open->label(),
                TicketStatus::Pending->label(),
                TicketStatus::Answered->label(),
                TicketStatus::Resolved->label(),
                TicketStatus::Closed->label(),
            ],
            'data' => [
                $kpis['open'],
                $kpis['pending'],
                $kpis['answered'],
                $kpis['resolved'],
                $kpis['closed'],
            ],
        ];

        // Semáforo de SLA (resolución) por ticket de la página actual.
        $resolver = app(\App\Domain\Tickets\Services\SlaResolver::class);
        $slaBadges = [];
        foreach ($tickets as $t) {
            $slaBadges[$t->id] = \App\Domain\Tickets\Services\SlaResolver::badge($resolver->resolutionStatus($t));
        }

        return view('livewire.admin.tickets.internal-ticket-index', [
            'tickets' => $tickets,
            'areas' => InternalArea::orderBy('name')->get(),
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
            'kpis' => $kpis,
            'chart' => $chart,
            'slaBadges' => $slaBadges,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
