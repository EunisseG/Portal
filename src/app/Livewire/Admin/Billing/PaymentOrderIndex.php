<?php

namespace App\Livewire\Admin\Billing;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Billing\Services\PaymentProofService;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\PaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Página "Órdenes de pago" del admin: lista los cobros (órdenes) con sus KPIs,
 * permite marcarlos como pagados, generar una orden nueva para un cliente y su
 * servicio, y subir un comprobante de pago a nombre de un cliente. Debajo
 * muestra los comprobantes recibidos para aprobar o rechazar.
 */
class PaymentOrderIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'pstatus')]
    public string $proofStatusFilter = '';

    // --- Marcar como pagada ---
    public ?int $markingId = null;
    public string $markMethod = 'transfer';
    public string $markReference = '';

    // --- Generar orden de pago (cliente + servicio) ---
    public bool $showOrderForm = false;
    public string $orderClientId = '';
    public string $orderServiceId = '';

    // --- Subir comprobante (a nombre de un cliente) ---
    public bool $showProofForm = false;
    public string $proofClientId = '';
    public string $proofOrderId = '';
    public $proofFile = null;
    public string $proofAmount = '';
    public string $proofMethod = 'transfer';
    public string $proofPaidAt = '';
    public string $proofReference = '';
    public bool $proofApproveNow = false;

    // --- Rechazo de comprobante ---
    public ?int $rejectingId = null;
    public string $rejectNote = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Invoice::class);
        $this->proofPaidAt = now()->toDateString();
    }

    public function canManageProofs(): bool
    {
        return Gate::allows('viewAny', PaymentProof::class);
    }

    public function canRecordPayments(): bool
    {
        return auth()->user()?->can('payments.record') ?? false;
    }

    public function updatingSearch(): void
    {
        $this->resetPage('ordenes');
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage('ordenes');
    }

    public function updatingProofStatusFilter(): void
    {
        $this->resetPage('proofs');
    }

    // ================= Marcar como pagada =================

    public function startMark(int $orderId): void
    {
        Gate::authorize('payments.record');
        $this->markingId = $orderId;
        $this->markMethod = 'transfer';
        $this->markReference = '';
    }

    public function cancelMark(): void
    {
        $this->markingId = null;
        $this->markReference = '';
    }

    public function confirmMark(PaymentOrderService $orders): void
    {
        Gate::authorize('payments.record');

        $order = PaymentOrder::findOrFail($this->markingId);

        if ($order->status === PaymentOrderStatus::Paid) {
            $this->cancelMark();
            session()->flash('status', 'La orden ya estaba pagada.');

            return;
        }

        $this->validate([
            'markMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'markReference' => ['nullable', 'string', 'max:255'],
        ], [], ['markMethod' => 'método de pago', 'markReference' => 'referencia']);

        $orders->markPaid($order, null, [
            'method' => $this->markMethod,
            'reference' => $this->markReference ?: null,
            'notes' => 'Orden marcada como pagada manualmente.',
            'recorded_by' => auth()->id(),
        ]);

        $this->cancelMark();
        session()->flash('status', 'Orden marcada como pagada.');
    }

    // ================= Generar orden de pago =================

    public function toggleOrderForm(): void
    {
        Gate::authorize('update', Invoice::class);
        $this->showOrderForm = ! $this->showOrderForm;
        $this->showProofForm = false;
        $this->reset('orderClientId', 'orderServiceId');
    }

    /** Al cambiar de cliente se limpia el servicio elegido. */
    public function updatedOrderClientId(): void
    {
        $this->orderServiceId = '';
    }

    public function createOrder(PaymentOrderService $orders, PaymentGateway $gateway): void
    {
        Gate::authorize('update', Invoice::class);

        $data = $this->validate([
            'orderClientId' => ['required', Rule::exists('clients', 'id')],
            'orderServiceId' => ['required', Rule::exists('services', 'id')],
        ], [], ['orderClientId' => 'cliente', 'orderServiceId' => 'servicio']);

        // El servicio debe pertenecer al cliente elegido.
        $service = Service::where('id', $data['orderServiceId'])
            ->where('client_id', $data['orderClientId'])
            ->first();

        if (! $service) {
            $this->addError('orderServiceId', 'El servicio no pertenece a ese cliente.');

            return;
        }

        try {
            $order = $orders->createForService($service, $gateway);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo generar la orden: '.$e->getMessage());

            return;
        }

        $this->reset('orderClientId', 'orderServiceId', 'showOrderForm');
        session()->flash('status', 'Orden de pago '.$order->folio.' generada.');
    }

    // ================= Subir comprobante =================

    public function toggleProofForm(): void
    {
        Gate::authorize('manage', PaymentProof::class);
        $this->showProofForm = ! $this->showProofForm;
        $this->showOrderForm = false;
        $this->reset('proofClientId', 'proofOrderId', 'proofFile', 'proofAmount', 'proofReference', 'proofApproveNow');
        $this->proofMethod = 'transfer';
        $this->proofPaidAt = now()->toDateString();
    }

    /** Al cambiar de cliente se limpia la orden y se precarga su monto si aplica. */
    public function updatedProofClientId(): void
    {
        $this->proofOrderId = '';
        $this->proofAmount = '';
    }

    /** Al elegir una orden, precarga su monto como importe del comprobante. */
    public function updatedProofOrderId(): void
    {
        if ($this->proofOrderId === '') {
            return;
        }

        $order = PaymentOrder::find($this->proofOrderId);
        if ($order) {
            $this->proofAmount = number_format($order->amount_cents / 100, 2, '.', '');
        }
    }

    public function submitProof(PaymentProofService $proofs): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $data = $this->validate([
            'proofClientId' => ['required', Rule::exists('clients', 'id')],
            'proofOrderId' => ['nullable', Rule::exists('payment_orders', 'id')],
            'proofFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'proofAmount' => ['required', 'numeric', 'min:0'],
            'proofMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'proofPaidAt' => ['nullable', 'date'],
            'proofReference' => ['nullable', 'string', 'max:255'],
        ], [], [
            'proofClientId' => 'cliente',
            'proofFile' => 'archivo',
            'proofAmount' => 'monto',
            'proofMethod' => 'método de pago',
        ]);

        // Si se indicó una orden, debe ser del cliente elegido.
        $order = null;
        if ($data['proofOrderId'] !== '' && $data['proofOrderId'] !== null) {
            $order = PaymentOrder::where('id', $data['proofOrderId'])
                ->where('client_id', $data['proofClientId'])
                ->first();
            if (! $order) {
                $this->addError('proofOrderId', 'La orden no pertenece a ese cliente.');

                return;
            }
        }

        $approveNow = $this->proofApproveNow && $this->canRecordPayments();

        $proofs->uploadByAdmin([
            'client_id' => (int) $data['proofClientId'],
            'payment_order' => $order,
            'file' => $this->proofFile,
            'amount' => $data['proofAmount'],
            'method' => $data['proofMethod'],
            'paid_at' => $data['proofPaidAt'] ?: null,
            'reference' => $data['proofReference'] ?: null,
            'approve' => $approveNow,
            'reviewed_by' => auth()->id(),
        ]);

        $this->reset('proofClientId', 'proofOrderId', 'proofFile', 'proofAmount', 'proofReference', 'proofApproveNow', 'showProofForm');
        $this->proofMethod = 'transfer';
        $this->proofPaidAt = now()->toDateString();
        session()->flash('status', $approveNow
            ? 'Comprobante subido y aprobado.'
            : 'Comprobante subido. Queda en revisión.');
    }

    // ================= Aprobar / rechazar comprobantes =================

    public function approveProof(int $proofId, PaymentProofService $proofs): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $proof = PaymentProof::with(['invoice', 'paymentOrder'])->findOrFail($proofId);
        $message = $proofs->approve($proof, auth()->user());
        session()->flash('status', $message);
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

    public function rejectProof(PaymentProofService $proofs): void
    {
        Gate::authorize('manage', PaymentProof::class);

        $this->validate(['rejectNote' => ['required', 'string', 'max:1000']], [], ['rejectNote' => 'motivo']);

        $proof = PaymentProof::findOrFail($this->rejectingId);
        $proofs->reject($proof, $this->rejectNote, auth()->user());

        $this->cancelReject();
        session()->flash('status', 'Comprobante rechazado.');
    }

    public function render(): View
    {
        $orders = PaymentOrder::query()
            ->with(['client.user', 'service', 'invoice'])
            ->withCount(['proofs as pending_proofs_count' => fn ($q) => $q->where('status', PaymentProofStatus::Pending->value)])
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
            ->paginate(15, ['*'], 'ordenes');

        $counts = PaymentOrder::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
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
            'data' => [$kpis['pending'], $kpis['paid'], $kpis['expired'], $kpis['cancelled']],
        ];

        // Comprobantes recibidos (solo para quien puede gestionarlos).
        $proofs = ! $this->canManageProofs() ? null : PaymentProof::query()
            ->with(['client.user', 'invoice', 'paymentOrder', 'reviewer'])
            ->when($this->proofStatusFilter !== '', fn ($q) => $q->where('status', $this->proofStatusFilter))
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'proofs');

        // Datos para los formularios (cliente + servicio / orden).
        $clients = Client::with('user')->get()->sortBy(fn ($c) => $c->user?->name)->values();
        $orderServices = $this->orderClientId !== ''
            ? Service::where('client_id', $this->orderClientId)->orderByDesc('id')->get(['id', 'name', 'total_cents', 'currency'])
            : collect();
        $proofOrders = $this->proofClientId !== ''
            ? PaymentOrder::where('client_id', $this->proofClientId)->orderByDesc('id')->get(['id', 'folio', 'amount_cents', 'currency'])
            : collect();

        return view('livewire.admin.billing.payment-order-index', [
            'orders' => $orders,
            'proofs' => $proofs,
            'markingOrder' => $this->markingId ? PaymentOrder::find($this->markingId) : null,
            'methodOptions' => PaymentMethod::options(),
            'statusOptions' => PaymentOrderStatus::options(),
            'proofStatusOptions' => PaymentProofStatus::options(),
            'canMarkPaid' => $this->canRecordPayments(),
            'canManageProofs' => $this->canManageProofs(),
            'canManageInvoices' => Gate::allows('update', Invoice::class),
            'clients' => $clients,
            'orderServices' => $orderServices,
            'proofOrders' => $proofOrders,
            'kpis' => $kpis,
            'chart' => $chart,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
