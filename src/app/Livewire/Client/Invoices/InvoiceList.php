<?php

namespace App\Livewire\Client\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Models\PaymentProof;
use App\Support\Money;
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

    /** Sección activa: 'invoices' (facturas) o 'proofs' (órdenes y comprobantes). */
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

    /**
     * @param  string|null  $section  'proofs' cuando se entra por «Comprobantes de
     *                                pago»; sin valor se muestran las facturas.
     */
    public function mount(?string $section = null): void
    {
        abort_unless(auth()->user()->canClient('client.invoices.view'), 403);

        if ($section !== null) {
            $this->tab = $section === 'proofs' ? 'proofs' : 'invoices';
        }
    }

    public function updatingOnlyPending(): void
    {
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

    public function render(): View
    {
        $client = auth()->user()->clientAccount();

        $invoices = null;
        $proofs = null;
        $orders = null;

        if ($this->tab === 'proofs') {
            // Órdenes de pago del cliente (viven en esta pestaña, no en Facturas).
            $orders = $client
                ? PaymentOrder::query()
                    ->where('client_id', $client->id)
                    ->orderByDesc('id')
                    ->paginate(10, ['*'], 'ordenes')
                : PaymentOrder::query()->whereRaw('1 = 0')->paginate(10, ['*'], 'ordenes');

            $proofs = $client
                ? PaymentProof::query()
                    ->where('client_id', $client->id)
                    ->with(['invoice', 'paymentOrder'])
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
            'orders' => $orders,
            'clientInvoices' => $clientInvoices,
            'methodOptions' => PaymentMethod::options(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
