<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Domain\Services\Services\DomainService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ServiceIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'services' o 'domains'. */
    #[Url(as: 'tab')]
    public string $tab = 'services';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    /** Filtro por tipo de servicio (solo pestaña Servicios). */
    #[Url(as: 'type')]
    public string $typeFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Service::class);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['services', 'domains'], true) ? $tab : 'services';
        // Los filtros de estado no son equivalentes entre pestañas; se limpian.
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

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /** Filtra por estado al hacer clic en una tarjeta KPI (servicios). */
    public function filterByStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function deleteDomain(int $domainId, DomainService $domains): void
    {
        Gate::authorize('delete', Domain::class);

        $domain = Domain::findOrFail($domainId);
        $domains->delete($domain);

        session()->flash('status', 'Dominio eliminado.');
    }

    public function render(): View
    {
        $services = null;
        $domains = null;
        $kpis = ['pending' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0, 'total' => 0];
        $chart = ['labels' => [], 'data' => []];
        $domainKpis = ['active' => 0, 'pending' => 0, 'expired' => 0, 'cancelled' => 0, 'total' => 0];
        $domainChart = ['labels' => [], 'data' => []];

        if ($this->tab === 'domains') {
            $domains = Domain::query()
                ->with(['client.user', 'service'])
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                            ->orWhereHas('client.user', function ($uq) {
                                $uq->where('name', 'like', "%{$this->search}%")
                                    ->orWhere('email', 'like', "%{$this->search}%");
                            });
                    });
                })
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderByDesc('id')
                ->paginate(10);

            // KPIs por estado del dominio.
            $dCounts = Domain::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $domainKpis = [
                'active' => (int) ($dCounts[DomainStatus::Active->value] ?? 0),
                'pending' => (int) ($dCounts[DomainStatus::Pending->value] ?? 0),
                'expired' => (int) ($dCounts[DomainStatus::Expired->value] ?? 0),
                'cancelled' => (int) ($dCounts[DomainStatus::Cancelled->value] ?? 0),
                'total' => (int) $dCounts->sum(),
            ];

            $domainChart = [
                'labels' => [
                    DomainStatus::Active->label(),
                    DomainStatus::Pending->label(),
                    DomainStatus::Expired->label(),
                    DomainStatus::Transferring->label(),
                    DomainStatus::Cancelled->label(),
                ],
                'data' => [
                    (int) ($dCounts[DomainStatus::Active->value] ?? 0),
                    (int) ($dCounts[DomainStatus::Pending->value] ?? 0),
                    (int) ($dCounts[DomainStatus::Expired->value] ?? 0),
                    (int) ($dCounts[DomainStatus::Transferring->value] ?? 0),
                    (int) ($dCounts[DomainStatus::Cancelled->value] ?? 0),
                ],
            ];
        } else {
            $services = Service::query()
                ->with(['client.user', 'server'])
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', "%{$this->search}%")
                            ->orWhereHas('client.user', function ($uq) {
                                $uq->where('name', 'like', "%{$this->search}%")
                                    ->orWhere('email', 'like', "%{$this->search}%");
                            });
                    });
                })
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
                ->orderByDesc('id')
                ->paginate(10);

            // Tarjetas KPI por estado del pedido.
            $counts = Service::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $kpis = [
                'pending' => (int) ($counts[ServiceStatus::Pending->value] ?? 0),
                'active' => (int) ($counts[ServiceStatus::Active->value] ?? 0),
                'suspended' => (int) ($counts[ServiceStatus::Suspended->value] ?? 0),
                'cancelled' => (int) ($counts[ServiceStatus::Cancelled->value] ?? 0),
                'total' => (int) $counts->sum(),
            ];

            // Datos para la gráfica de distribución de pedidos por estado.
            $chart = [
                'labels' => [
                    ServiceStatus::Active->label(),
                    ServiceStatus::Pending->label(),
                    ServiceStatus::Suspended->label(),
                    ServiceStatus::Cancelled->label(),
                ],
                'data' => [
                    $kpis['active'],
                    $kpis['pending'],
                    $kpis['suspended'],
                    $kpis['cancelled'],
                ],
            ];
        }

        return view('livewire.admin.services.service-index', [
            'services' => $services,
            'domains' => $domains,
            'statusOptions' => ServiceStatus::options(),
            'typeOptions' => ServiceType::options(),
            'domainStatusOptions' => DomainStatus::options(),
            'kpis' => $kpis,
            'chart' => $chart,
            'domainKpis' => $domainKpis,
            'domainChart' => $domainChart,
            'clientsForExport' => \App\Domain\Clients\Models\Client::with('user')
                ->get()
                ->sortBy(fn ($c) => $c->user->name)
                ->values(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
