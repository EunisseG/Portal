<?php

namespace App\Livewire\Admin\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de facturas (CFDI) del admin. Los comprobantes de pago y las órdenes
 * viven en su propia página (Pagos → Órdenes de pago).
 */
class InvoiceIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'pending')]
    public bool $onlyPending = false;

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

    public function updatingOnlyPending(): void
    {
        $this->resetPage();
    }

    public function changeStatus(int $invoiceId, string $status, InvoiceService $invoices): void
    {
        Gate::authorize('update', Invoice::class);

        $enum = InvoiceStatus::tryFrom($status);
        if (! $enum) {
            return;
        }

        $invoice = Invoice::findOrFail($invoiceId);
        $invoices->changeStatus($invoice, $enum);

        session()->flash('status', 'Estado de la factura actualizado.');
    }

    public function delete(int $invoiceId, InvoiceService $invoices): void
    {
        Gate::authorize('delete', Invoice::class);

        $invoice = Invoice::findOrFail($invoiceId);
        $invoices->delete($invoice);

        session()->flash('status', 'Factura eliminada.');
    }

    public function render(): View
    {
        $invoices = Invoice::query()
            ->with('client.user')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('folio', 'like', "%{$this->search}%")
                        ->orWhere('uuid', 'like', "%{$this->search}%")
                        ->orWhereHas('client.user', function ($uq) {
                            $uq->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->onlyPending, fn ($q) => $q->whereIn('status', [
                InvoiceStatus::Pending->value,
                InvoiceStatus::Overdue->value,
            ]))
            ->orderByDesc('id')
            ->paginate(10);

        $counts = Invoice::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $kpis = [
            'pending' => (int) ($counts[InvoiceStatus::Pending->value] ?? 0),
            'paid' => (int) ($counts[InvoiceStatus::Paid->value] ?? 0),
            'overdue' => (int) ($counts[InvoiceStatus::Overdue->value] ?? 0),
            'cancelled' => (int) ($counts[InvoiceStatus::Cancelled->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];

        $chart = [
            'labels' => [
                InvoiceStatus::Pending->label(),
                InvoiceStatus::Paid->label(),
                InvoiceStatus::Overdue->label(),
                InvoiceStatus::Cancelled->label(),
            ],
            'data' => [
                $kpis['pending'],
                $kpis['paid'],
                $kpis['overdue'],
                $kpis['cancelled'],
            ],
        ];

        return view('livewire.admin.invoices.invoice-index', [
            'invoices' => $invoices,
            'statusOptions' => InvoiceStatus::options(),
            'kpis' => $kpis,
            'chart' => $chart,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
