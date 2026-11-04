<?php

namespace App\Livewire\Admin\Tickets;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TicketIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'active' (abiertos/respondidos/en espera) o 'closed'. */
    #[Url(as: 'tab')]
    public string $tab = 'active';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'priority')]
    public string $priorityFilter = '';

    #[Url(as: 'crit')]
    public string $criticalityFilter = '';

    #[Url(as: 'dept')]
    public string $departmentFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Ticket::class);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'closed' ? 'closed' : 'active';
        // El filtro de estado no aplica igual entre pestañas; se limpia.
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPriorityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCriticalityFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        // Estados considerados "activos" (todo menos cerrado).
        $activeStatuses = [
            TicketStatus::Open->value,
            TicketStatus::Pending->value,
            TicketStatus::Answered->value,
            TicketStatus::Resolved->value,
        ];

        $tickets = Ticket::query()
            ->where('type', \App\Domain\Tickets\Enums\TicketType::Client->value)
            ->with('client.user')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('number', 'like', "%{$this->search}%")
                        ->orWhere('subject', 'like', "%{$this->search}%")
                        ->orWhereHas('client.user', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            // Pestaña: cerrados vs activos.
            ->when($this->tab === 'closed',
                fn ($q) => $q->where('status', TicketStatus::Closed->value),
                fn ($q) => $q->whereIn('status', $activeStatuses),
            )
            // El filtro de estado solo aplica en la pestaña de activos.
            ->when($this->tab !== 'closed' && $this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->criticalityFilter !== '', fn ($q) => $q->where('criticality', $this->criticalityFilter))
            ->when($this->departmentFilter !== '', fn ($q) => $q->where('department', $this->departmentFilter))
            ->orderByDesc('last_reply_at')
            ->paginate(10);

        // Semáforo de SLA (resolución) por ticket de la página actual.
        $resolver = app(\App\Domain\Tickets\Services\SlaResolver::class);
        $slaBadges = [];
        foreach ($tickets as $t) {
            $slaBadges[$t->id] = \App\Domain\Tickets\Services\SlaResolver::badge($resolver->resolutionStatus($t));
        }

        // Opciones de estado para la pestaña de activos (sin "Cerrado").
        $activeStatusOptions = collect(TicketStatus::options())
            ->reject(fn ($label, $value) => $value === TicketStatus::Closed->value)
            ->all();

        // KPIs y gráfica de distribución de tickets por estado.
        $counts = Ticket::where('type', \App\Domain\Tickets\Enums\TicketType::Client->value)
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

        return view('livewire.admin.tickets.ticket-index', [
            'tickets' => $tickets,
            'statusOptions' => $activeStatusOptions,
            'priorityOptions' => TicketPriority::options(),
            'criticalityOptions' => TicketCriticality::options(),
            'departmentOptions' => TicketDepartment::options(),
            'kpis' => $kpis,
            'chart' => $chart,
            'slaBadges' => $slaBadges,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
