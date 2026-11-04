<?php

namespace App\Livewire\Admin\Clients;

use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** Filtro por ejecutivo de ventas: '' (todos), '0' (sin asignar) o id. */
    #[Url(as: 'rep')]
    public string $salesRepFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Client::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSalesRepFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Elimina por completo una cuenta de cliente (titular, sub-usuarios y todos
     * sus datos ligados). Acción irreversible, protegida por permiso.
     */
    public function delete(int $clientId, ClientService $clients): void
    {
        Gate::authorize('delete', Client::class);

        $client = Client::findOrFail($clientId);
        $name = $client->user?->fullName() ?: $client->user?->name ?: 'Cliente';

        try {
            $clients->delete($client);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bloqueo por salvaguarda fiscal (facturas timbradas).
            session()->flash('error', $e->validator->errors()->first());

            return;
        }

        session()->flash('status', 'Cliente eliminado: '.$name.'.');
    }

    /** Cambia el estado del cliente (suspender / cancelar / reactivar). */
    public function setStatus(int $clientId, string $status, ClientService $clients): void
    {
        Gate::authorize('update', Client::class);

        $target = ClientStatus::tryFrom($status);
        if ($target === null) {
            return;
        }

        $client = Client::findOrFail($clientId);
        $clients->setStatus($client, $target);

        session()->flash('status', 'Estado del cliente actualizado a "'.$target->label().'".');
    }

    public function render(): View
    {
        $clients = Client::query()
            ->with('user', 'services.salesRep')
            // Cuenta de facturas timbradas por cliente, para habilitar o no el
            // borrado (salvaguarda fiscal) sin consultas N+1.
            ->withCount(['invoices as stamped_invoices_count' => function ($q) {
                $q->whereNotNull('uuid')->where('uuid', '!=', '');
            }])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('company', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            // Filtro por ejecutivo (a través de los servicios del cliente):
            // '0' = sin ningún servicio con ejecutivo; cualquier otro = clientes
            // con al menos un servicio llevado por ese ejecutivo.
            ->when($this->salesRepFilter !== '', function ($query) {
                if ($this->salesRepFilter === '0') {
                    $query->whereDoesntHave('services', function ($s) {
                        $s->whereNotNull('sales_rep_id');
                    });
                } else {
                    $query->whereHas('services', function ($s) {
                        $s->where('sales_rep_id', (int) $this->salesRepFilter);
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate(10);

        // KPIs por estado del cliente (Activo / Suspendido / Cancelado / Total).
        $active = Client::where('status', ClientStatus::Active->value)->count();
        $suspended = Client::where('status', ClientStatus::Suspended->value)->count();
        $cancelled = Client::where('status', ClientStatus::Cancelled->value)->count();
        $total = Client::count();

        // Datos para la gráfica de distribución de clientes por estado.
        $chart = [
            'labels' => [
                ClientStatus::Active->label(),
                ClientStatus::Suspended->label(),
                ClientStatus::Cancelled->label(),
            ],
            'data' => [$active, $suspended, $cancelled],
        ];

        return view('livewire.admin.clients.client-index', [
            'clients' => $clients,
            'kpiActive' => $active,
            'kpiSuspended' => $suspended,
            'kpiCancelled' => $cancelled,
            'kpiTotal' => $total,
            'chart' => $chart,
            'salesReps' => \App\Domain\Sales\Models\SalesRep::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
