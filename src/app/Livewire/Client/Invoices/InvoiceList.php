<?php

namespace App\Livewire\Client\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentProof;
use App\Integrations\Contracts\PaymentGateway;
use App\Support\Money;
use App\Support\OperationMode;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class InvoiceList extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** Pestaña activa: 'invoices' o 'proofs'. */
    #[Url(as: 'tab')]
    public string $tab = 'invoices';

    #[Url(as: 'pending')]
    public bool $onlyPending = false;

    // Formulario de nuevo comprobante.
    public bool $showProofForm = false;
    public $proofFile = null;
    public string $proofAmount = '';
    public string $proofMethod = 'transfer';
    public string $proofPaidAt = '';
    public string $proofReference = '';
    public string $proofInvoiceId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->canClient('client.invoices.view'), 403);
    }

    public function updatingOnlyPending(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'proofs' ? 'proofs' : 'invoices';
        $this->resetPage();
    }

    public function submitProof(): void
    {
        $client = auth()->user()->clientAccount();
        abort_unless($client !== null, 403);
        // Subir comprobantes requiere el permiso correspondiente (sub-usuarios).
        abort_unless(auth()->user()->canClient('client.proofs.upload'), 403);

        $data = $this->validate([
            'proofFile' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'proofAmount' => ['required', 'numeric', 'min:0'],
            'proofMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'proofPaidAt' => ['nullable', 'date'],
            'proofReference' => ['nullable', 'string', 'max:255'],
            // La factura, si se indica, debe ser del propio cliente.
            'proofInvoiceId' => ['nullable', Rule::exists('invoices', 'id')->where('client_id', $client->id)],
        ], [], [
            'proofFile' => 'archivo',
            'proofAmount' => 'monto',
            'proofMethod' => 'método de pago',
        ]);

        $stored = $this->proofFile->store('payment-proofs/'.$client->id, 'local');

        PaymentProof::create([
            'client_id' => $client->id,
            'invoice_id' => $data['proofInvoiceId'] ?: null,
            'file_path' => $stored,
            'original_name' => $this->proofFile->getClientOriginalName(),
            'amount_cents' => Money::fromDecimal($data['proofAmount'])->amountInCents,
            'currency' => Money::DEFAULT_CURRENCY,
            'method' => $data['proofMethod'],
            'paid_at' => $data['proofPaidAt'] ?: null,
            'reference' => $data['proofReference'] ?: null,
            'status' => \App\Domain\Billing\Enums\PaymentProofStatus::Pending->value,
        ]);

        $this->reset('proofFile', 'proofAmount', 'proofPaidAt', 'proofReference', 'proofInvoiceId', 'showProofForm');
        $this->proofMethod = 'transfer';
        session()->flash('status', 'Comprobante enviado. Lo revisaremos y te emitiremos tu factura.');
    }

    /**
     * Inicia el pago en línea de una factura. Solo disponible cuando el modo de
     * pagos es automático y hay una pasarela configurada. Preparado para
     * conectar la pasarela (createCharge); por ahora deja listo el punto de
     * enganche y avisa si falta configuración.
     */
    public function payOnline(int $invoiceId, OperationMode $mode, PaymentGateway $gateway): void
    {
        $client = auth()->user()->clientAccount();
        abort_unless($client !== null, 403);

        // El pago en línea solo opera en modo automático.
        if (! $mode->paymentsAutomatic()) {
            session()->flash('error', 'El pago en línea no está habilitado. Sube tu comprobante de pago.');

            return;
        }

        $invoice = Invoice::where('client_id', $client->id)->findOrFail($invoiceId);

        // No tiene sentido cobrar una factura ya pagada o sin saldo.
        if ($invoice->balanceCents() <= 0) {
            session()->flash('error', 'Esta factura no tiene saldo pendiente.');

            return;
        }

        // Requiere una pasarela realmente configurada (no el fake).
        if (! method_exists($gateway, 'isConfigured') || ! $gateway->isConfigured()) {
            session()->flash('error', 'Aún no hay una pasarela de pago configurada. Inténtalo más tarde o sube tu comprobante.');

            return;
        }

        // Genera una orden de pago (folio de cobro) y redirige al checkout de
        // la pasarela. La confirmación la hace el webhook.
        try {
            $order = app(\App\Domain\Billing\Services\PaymentOrderService::class)->create($invoice, $gateway);
        } catch (\Throwable $e) {
            session()->flash('error', 'No se pudo iniciar el pago en línea: '.$e->getMessage());

            return;
        }

        if ($order->checkout_url) {
            $this->redirect($order->checkout_url);

            return;
        }

        session()->flash('error', 'No se pudo iniciar el pago en línea.');
    }

    public function render(OperationMode $mode): View
    {
        $client = auth()->user()->clientAccount();

        $invoices = null;
        $proofs = null;

        if ($this->tab === 'proofs') {
            $proofs = $client
                ? PaymentProof::query()
                    ->where('client_id', $client->id)
                    ->with('invoice')
                    ->orderByDesc('id')
                    ->paginate(10)
                : PaymentProof::query()->whereRaw('1 = 0')->paginate(10);
        } else {
            $invoices = $client
                ? Invoice::query()
                    ->where('client_id', $client->id)
                    ->when($this->onlyPending, fn ($q) => $q->whereIn('status', [
                        InvoiceStatus::Pending->value,
                        InvoiceStatus::Overdue->value,
                    ]))
                    ->orderByDesc('issued_at')
                    ->orderByDesc('id')
                    ->paginate(10)
                : Invoice::query()->whereRaw('1 = 0')->paginate(10);
        }

        // Facturas del cliente para el selector opcional del comprobante.
        $clientInvoices = $client
            ? Invoice::where('client_id', $client->id)->orderByDesc('id')->get(['id', 'folio', 'total_cents', 'currency'])
            : collect();

        return view('livewire.client.invoices.invoice-list', [
            'invoices' => $invoices,
            'proofs' => $proofs,
            'clientInvoices' => $clientInvoices,
            'methodOptions' => PaymentMethod::options(),
            'paymentsAutomatic' => $mode->paymentsAutomatic(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
