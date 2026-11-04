<?php

namespace App\Livewire\Admin\Billing;

use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado (solo lectura) de las órdenes de pago en línea. Permite auditar los
 * folios de cobro, su estado y a qué factura corresponden. No es la factura
 * CFDI; es el intento de cobro por pasarela.
 */
class PaymentOrderIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $orders = PaymentOrder::query()
            ->with(['client.user', 'invoice'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($qq) {
                    $qq->where('folio', 'like', '%'.$this->search.'%')
                        ->orWhere('external_reference', 'like', '%'.$this->search.'%')
                        ->orWhereHas('client.user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(15);

        // KPIs y gráfica de distribución de órdenes de pago por estado.
        $counts = PaymentOrder::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpis = [
            'pending' => (int) ($counts[PaymentOrderStatus::Pending->value] ?? 0),
            'paid' => (int) ($counts[PaymentOrderStatus::Paid->value] ?? 0),
            'expired' => (int) ($counts[PaymentOrderStatus::Expired->value] ?? 0),
            'cancelled' => (int) ($counts[PaymentOrderStatus::Cancelled->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];

        $chart = [
            'labels' => [
                PaymentOrderStatus::Pending->label(),
                PaymentOrderStatus::Paid->label(),
                PaymentOrderStatus::Expired->label(),
                PaymentOrderStatus::Cancelled->label(),
            ],
            'data' => [
                $kpis['pending'],
                $kpis['paid'],
                $kpis['expired'],
                $kpis['cancelled'],
            ],
        ];

        return view('livewire.admin.billing.payment-order-index', [
            'orders' => $orders,
            'statusOptions' => PaymentOrderStatus::options(),
            'kpis' => $kpis,
            'chart' => $chart,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
