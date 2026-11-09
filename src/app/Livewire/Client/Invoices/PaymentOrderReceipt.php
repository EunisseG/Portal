<?php

namespace App\Livewire\Client\Invoices;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Billing\Services\PaymentReceiptData;
use App\Integrations\Contracts\PaymentGateway;
use App\Support\OperationMode;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Recibo de una orden de pago para el cliente: muestra los datos del cliente y
 * del emisor, el desglose (subtotal, IVA, total), el detalle de la compra y de
 * la orden, y un botón "Realizar pago" que lleva al checkout de la pasarela.
 * También permite subir el comprobante de pago, que el administrador revisa y,
 * al aprobarlo, marca la orden como pagada.
 *
 * La orden nace del pedido (Service); esta pantalla no crea órdenes.
 */
class PaymentOrderReceipt extends Component
{
    use WithFileUploads;

    public PaymentOrder $order;

    // Formulario del comprobante de pago.
    public $proofFile = null;
    public string $proofMethod = 'transfer';
    public string $proofPaidAt = '';
    public string $proofReference = '';

    public function mount(PaymentOrder $order): void
    {
        abort_unless(auth()->user()->canClient('client.invoices.view'), 403);

        $client = auth()->user()->clientAccount();
        abort_unless($client !== null, 403);

        // Solo el cliente dueño puede ver el recibo de su orden.
        abort_unless((int) $order->client_id === (int) $client->id, 403);

        $this->order = $order;
        $this->proofPaidAt = now()->toDateString();
    }

    /**
     * Lleva al checkout de la pasarela para pagar la orden. Solo opera en modo
     * automático, con orden pendiente, saldo por cobrar y checkout disponible.
     */
    public function pay(OperationMode $mode, PaymentOrderService $orders, PaymentGateway $gateway): void
    {
        if (! $mode->paymentsAutomatic()) {
            session()->flash('error', 'El pago en línea no está habilitado.');

            return;
        }

        if (! $this->order->isPending() || (int) $this->order->amount_cents <= 0) {
            session()->flash('error', 'Esta orden no tiene saldo pendiente por pagar.');

            return;
        }

        // Si la orden se creó antes de activar el pago en línea, el enlace de
        // pago se genera ahora, al conectar con la pasarela.
        try {
            $this->order = $orders->ensureCheckout($this->order, $gateway);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo iniciar el pago en línea: '.$e->getMessage());

            return;
        }

        if (! $this->order->checkout_url) {
            session()->flash('error', 'Aún no hay un enlace de pago disponible para esta orden.');

            return;
        }

        $this->redirect($this->order->checkout_url);
    }

    /**
     * Sube el comprobante de pago de esta orden. Queda "en revisión" hasta que
     * el administrador lo aprueba (lo que marca la orden como pagada).
     */
    public function submitProof(): void
    {
        $client = auth()->user()->clientAccount();
        abort_unless($client !== null, 403);
        abort_unless(auth()->user()->canClient('client.proofs.upload'), 403);

        if (! $this->canUploadProof()) {
            session()->flash('error', 'Esta orden no admite comprobantes en este momento.');

            return;
        }

        $data = $this->validate([
            'proofFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'proofMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'proofPaidAt' => ['nullable', 'date'],
            'proofReference' => ['nullable', 'string', 'max:255'],
        ], [], [
            'proofFile' => 'archivo',
            'proofMethod' => 'método de pago',
            'proofPaidAt' => 'fecha de pago',
            'proofReference' => 'referencia',
        ]);

        app(\App\Domain\Billing\Services\PaymentProofService::class)->uploadByClient(
            $this->order,
            $this->proofFile,
            $data['proofMethod'],
            $data['proofPaidAt'] ?: null,
            $data['proofReference'] ?: null,
        );

        $this->reset('proofFile', 'proofReference');
        $this->proofMethod = 'transfer';
        session()->flash('status', 'Comprobante enviado. Lo revisaremos y marcaremos tu orden como pagada.');
    }

    /** ¿Se puede subir un comprobante? Orden pendiente, con monto y sin otro en revisión. */
    public function canUploadProof(): bool
    {
        return $this->order->isPending()
            && (int) $this->order->amount_cents > 0
            && ! $this->order->proofs()->where('status', PaymentProofStatus::Pending->value)->exists();
    }

    public function render(
        OperationMode $mode,
        PaymentReceiptData $receiptData,
        PaymentOrderService $orders,
        PaymentGateway $gateway,
    ): View {
        $data = $receiptData->build($this->order);

        $payable = $this->order->isPending() && (int) $this->order->amount_cents > 0;
        $proofs = $this->order->proofs()->latest('id')->get();

        return view('livewire.client.invoices.payment-order-receipt', array_merge($data, [
            'paymentsAutomatic' => $mode->paymentsAutomatic(),
            'payable' => $payable,
            // Se puede pagar en línea si ya hay enlace o si puede generarse.
            'hasCheckout' => ! empty($this->order->checkout_url) || $orders->canCharge($gateway),
            'proofs' => $proofs,
            'canUploadProof' => auth()->user()->canClient('client.proofs.upload')
                && $payable
                && ! $proofs->contains(fn ($p) => $p->status === PaymentProofStatus::Pending),
            'hasPendingProof' => $proofs->contains(fn ($p) => $p->status === PaymentProofStatus::Pending),
            'methodOptions' => PaymentMethod::options(),
        ]))->layout('layouts.app', ['area' => 'client']);
    }
}
