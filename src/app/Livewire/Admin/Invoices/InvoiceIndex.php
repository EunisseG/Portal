<?php

namespace App\Livewire\Admin\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Billing\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class InvoiceIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'invoices' o 'proofs'. */
    #[Url(as: 'tab')]
    public string $tab = 'invoices';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'pending')]
    public bool $onlyPending = false;

    // --- Revisión de comprobantes (pestaña "proofs") ---
    #[Url(as: 'pstatus')]
    public string $proofStatusFilter = '';

    /** Comprobante seleccionado para rechazar (muestra el formulario de nota). */
    public ?int $rejectingId = null;

    public string $rejectNote = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
    }

    /** ¿El usuario puede ver/gestionar comprobantes? (para mostrar la pestaña) */
    public function canManageProofs(): bool
    {
        return Gate::allows('viewAny', PaymentProof::class);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'proofs' ? 'proofs' : 'invoices';
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

    public function updatingOnlyPending(): void
    {
        $this->resetPage();
    }

    public function updatingProofStatusFilter(): void
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

    // ------- Acciones de comprobantes -------

    /**
     * Aprueba el comprobante. Si $registerPayment es true y está ligado a una
     * factura, además registra el pago y actualiza el saldo/estado.
     */
    public function approveProof(int $proofId, bool $registerPayment = false): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $proof = PaymentProof::with('invoice')->findOrFail($proofId);
        $proof->update([
            'status' => PaymentProofStatus::Approved,
            'review_note' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($registerPayment && $proof->invoice) {
            if (auth()->user()?->can('payments.record')) {
                app(PaymentService::class)->record($proof->invoice, [
                    'amount_cents' => (int) $proof->amount_cents,
                    'method' => $proof->method?->value ?? 'other',
                    'paid_at' => ($proof->paid_at ?? now())->toDateString(),
                    'reference' => $proof->reference,
                    'payment_proof_id' => $proof->id,
                ]);

                session()->flash('status', 'Comprobante aprobado y pago registrado.');

                return;
            }

            session()->flash('status', 'Comprobante aprobado. No tienes permiso para registrar el pago.');

            return;
        }

        session()->flash('status', 'Comprobante aprobado.');
    }

    public function startReject(int $proofId): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $this->rejectingId = $proofId;
        $this->rejectNote = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectNote = '';
    }

    public function rejectProof(): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $this->validate([
            'rejectNote' => ['required', 'string', 'max:1000'],
        ], [], [
            'rejectNote' => 'motivo',
        ]);

        $proof = PaymentProof::findOrFail($this->rejectingId);
        $proof->update([
            'status' => PaymentProofStatus::Rejected,
            'review_note' => $this->rejectNote,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->cancelReject();
        session()->flash('status', 'Comprobante rechazado.');
    }

    public function render(): View
    {
        $invoices = null;
        $proofs = null;

        if ($this->tab === 'proofs' && $this->canManageProofs()) {
            $proofs = PaymentProof::query()
                ->with(['client.user', 'invoice', 'reviewer'])
                ->when($this->search !== '', function ($query) {
                    $query->where(function ($q) {
                        $q->where('reference', 'like', "%{$this->search}%")
                            ->orWhere('original_name', 'like', "%{$this->search}%")
                            ->orWhereHas('client.user', function ($uq) {
                                $uq->where('name', 'like', "%{$this->search}%")
                                    ->orWhere('email', 'like', "%{$this->search}%");
                            });
                    });
                })
                ->when($this->proofStatusFilter !== '', fn ($q) => $q->where('status', $this->proofStatusFilter))
                ->orderByDesc('id')
                ->paginate(10);
        } else {
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
        }

        // KPIs y gráfica de distribución de facturas por estado.
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
            'proofs' => $proofs,
            'statusOptions' => InvoiceStatus::options(),
            'proofStatusOptions' => PaymentProofStatus::options(),
            'canManageProofs' => $this->canManageProofs(),
            'kpis' => $kpis,
            'chart' => $chart,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
