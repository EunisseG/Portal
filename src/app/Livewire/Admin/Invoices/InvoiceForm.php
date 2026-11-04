<?php

namespace App\Livewire\Admin\Invoices;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\InvoiceExtractor;
use App\Domain\Billing\Services\InvoiceService;
use App\Domain\Clients\Models\Client;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class InvoiceForm extends Component
{
    use WithFileUploads;

    public ?Invoice $invoice = null;

    public string $client_id = '';
    public string $folio = '';
    public string $uuid = '';
    public string $total = '';
    public string $status = 'pending';
    public string $issued_at = '';
    public string $due_at = '';
    public string $notes = '';

    /** @var TemporaryUploadedFile|null */
    public $pdf = null;

    /** @var TemporaryUploadedFile|null */
    public $xml = null;

    // Mensaje informativo sobre el resultado de la extracción automática.
    public string $extractionNote = '';

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice && $invoice->exists) {
            Gate::authorize('update', Invoice::class);
            $this->invoice = $invoice;
            $this->client_id = (string) $invoice->client_id;
            $this->folio = (string) $invoice->folio;
            $this->uuid = (string) $invoice->uuid;
            $this->total = number_format($invoice->total_cents / 100, 2, '.', '');
            $this->status = $invoice->status->value;
            $this->issued_at = $invoice->issued_at?->toDateString() ?? '';
            $this->due_at = $invoice->due_at?->toDateString() ?? '';
            $this->notes = (string) $invoice->notes;
        } else {
            Gate::authorize('create', Invoice::class);

            $clientId = request()->query('client');
            if ($clientId && Client::whereKey($clientId)->exists()) {
                $this->client_id = (string) $clientId;
            }
        }
    }

    public function isEditing(): bool
    {
        return $this->invoice !== null;
    }

    // Al subir el XML (CFDI), extrae datos exactos.
    public function updatedXml(InvoiceExtractor $extractor): void
    {
        $this->validateOnly('xml');

        if (! $this->xml) {
            return;
        }

        $data = $extractor->fromXml(file_get_contents($this->xml->getRealPath()));
        $this->applyExtracted($data, 'XML CFDI');
    }

    // Al subir el PDF, intenta extracción best-effort (solo si aún no hay datos del XML).
    public function updatedPdf(InvoiceExtractor $extractor): void
    {
        $this->validateOnly('pdf');

        if (! $this->pdf) {
            return;
        }

        $data = $extractor->fromPdf($this->pdf->getRealPath());
        $this->applyExtracted($data, 'PDF');
    }

    private function applyExtracted(\App\Domain\Billing\Services\ExtractedInvoiceData $data, string $sourceLabel): void
    {
        $filled = [];

        if ($data->folio && $this->folio === '') {
            $this->folio = $data->folio;
            $filled[] = 'folio';
        }
        if ($data->uuid && $this->uuid === '') {
            $this->uuid = $data->uuid;
            $filled[] = 'UUID';
        }
        if ($data->total !== null && $this->total === '') {
            $this->total = number_format($data->total, 2, '.', '');
            $filled[] = 'total';
        }
        if ($data->issuedAt && $this->issued_at === '') {
            $this->issued_at = $data->issuedAt;
            $filled[] = 'fecha';
        }

        $this->extractionNote = $filled === []
            ? "No se pudo autocompletar desde el {$sourceLabel}. Captura los datos manualmente."
            : 'Autocompletado desde '.$sourceLabel.': '.implode(', ', $filled).'. Revisa y ajusta si es necesario.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')],
            'folio' => ['nullable', 'string', 'max:255'],
            'uuid' => ['nullable', 'string', 'max:255'],
            'total' => ['required', 'numeric', 'min:0'],
            'status' => ['required', new Enum(InvoiceStatus::class)],
            'issued_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // PDF obligatorio al crear; opcional al editar (reemplazo).
            'pdf' => [$this->isEditing() ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:10240'],
            'xml' => ['nullable', 'file', 'mimes:xml,txt', 'max:5120'],
        ];
    }

    public function save(InvoiceService $invoices): mixed
    {
        $data = $this->validate();

        $payload = [
            'client_id' => $data['client_id'],
            'folio' => $data['folio'] ?: null,
            'uuid' => $data['uuid'] ?: null,
            'total' => $data['total'],
            'status' => $data['status'],
            'issued_at' => $data['issued_at'] ?: null,
            'due_at' => $data['due_at'] ?: null,
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->isEditing()) {
            $invoices->update($this->invoice, $payload, $this->pdf, $this->xml);
            session()->flash('status', 'Factura actualizada.');
        } else {
            $invoices->create($payload, $this->pdf, $this->xml);
            session()->flash('status', 'Factura subida correctamente.');
        }

        return $this->redirect(route('admin.invoices.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.invoices.invoice-form', [
            'clients' => Client::with('user')->get()->sortBy(fn ($c) => $c->user->name)->values(),
            'statusOptions' => InvoiceStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
