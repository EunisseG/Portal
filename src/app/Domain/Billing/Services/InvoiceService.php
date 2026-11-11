<?php

namespace App\Domain\Billing\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Service;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /** Disco privado donde se guardan los archivos de factura. */
    public const DISK = 'local';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{
     *     client_id: int|string, folio?: ?string, uuid?: ?string,
     *     total: float|string, status: string,
     *     issued_at?: ?string, due_at?: ?string, notes?: ?string
     * }  $data
     */
    public function create(array $data, UploadedFile $pdf, ?UploadedFile $xml = null): Invoice
    {
        return DB::transaction(function () use ($data, $pdf, $xml): Invoice {
            $pdfPath = $pdf->store('invoices/'.$data['client_id'], self::DISK);
            $xmlPath = $xml?->store('invoices/'.$data['client_id'], self::DISK) ?: null;

            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'folio' => $data['folio'] ?? null,
                'uuid' => $data['uuid'] ?? null,
                'total_cents' => Money::fromDecimal($data['total'])->amountInCents,
                'currency' => Money::DEFAULT_CURRENCY,
                'status' => $data['status'],
                'issued_at' => $data['issued_at'] ?: null,
                'due_at' => $data['due_at'] ?: null,
                'pdf_path' => $pdfPath,
                'xml_path' => $xmlPath,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log(AuditAction::InvoiceCreated, $invoice, null, $this->snapshot($invoice));

            // Avisa al cliente que su factura ya está disponible (correo + WhatsApp).
            \App\Domain\Notifications\Jobs\SendInvoiceAvailableNotification::dispatch($invoice->id)->afterCommit();

            return $invoice;
        });
    }

    /**
     * Genera una factura a partir de un pedido (Service), con sus líneas:
     * el servicio base, cada complemento activo y, si aplica, una línea de
     * descuento (negativa). El total coincide con el total del pedido. La
     * factura queda pendiente y sin PDF (se cargará después).
     */
    public function createFromService(Service $service, ?string $dueAt = null): Invoice
    {
        return DB::transaction(function () use ($service, $dueAt): Invoice {
            $service->loadMissing(['addons', 'plan']);
            $currency = $service->currency ?? Money::DEFAULT_CURRENCY;

            $invoice = Invoice::create([
                'client_id' => $service->client_id,
                'service_id' => $service->id,
                'total_cents' => (int) $service->total_cents,
                'amount_paid_cents' => 0,
                'currency' => $currency,
                'status' => InvoiceStatus::Pending->value,
                'issued_at' => now()->toDateString(),
                'due_at' => $dueAt ?: now()->addDays(15)->toDateString(),
                'notes' => 'Generada desde el pedido #'.$service->id.' ('.$service->name.').',
            ]);

            // Línea del servicio base (precio unitario del pedido).
            $baseCents = (int) ($service->unit_price_cents ?? 0);
            $invoice->items()->create([
                'description' => $service->plan?->name ?: $service->name,
                'quantity' => 1,
                'unit_price_cents' => $baseCents,
                'amount_cents' => $baseCents,
                'currency' => $currency,
            ]);

            // Una línea por cada complemento que se cobra: los activos, sin los
            // de pago único ya pagados (los cancelados y vencidos no se facturan).
            foreach ($service->billableAddons() as $addon) {
                $invoice->items()->create([
                    'description' => 'Complemento: '.$addon->name,
                    'quantity' => 1,
                    'unit_price_cents' => (int) $addon->price_cents,
                    'amount_cents' => (int) $addon->price_cents,
                    'currency' => $currency,
                ]);
            }

            // Línea de descuento (negativa) si el pedido tiene promoción.
            if ((int) ($service->discount_cents ?? 0) > 0) {
                $label = $service->promotion?->name ?: 'Descuento';
                $invoice->items()->create([
                    'description' => 'Descuento: '.$label,
                    'quantity' => 1,
                    'unit_price_cents' => 0,
                    'amount_cents' => -1 * (int) $service->discount_cents,
                    'currency' => $currency,
                ]);
            }

            $this->audit->log(AuditAction::InvoiceCreated, $invoice, null, $this->snapshot($invoice));

            return $invoice;
        });
    }

    /**
     * Genera la factura de una orden de pago a partir de SUS líneas (p. ej. la
     * orden solo de complementos de un pedido ya pagado). El total es el de la
     * orden. Queda pendiente y sin PDF (se cargará después).
     */
    public function createFromPaymentOrder(\App\Domain\Billing\Models\PaymentOrder $order, ?string $dueAt = null): Invoice
    {
        return DB::transaction(function () use ($order, $dueAt): Invoice {
            $order->loadMissing('items');
            $currency = $order->currency ?? Money::DEFAULT_CURRENCY;

            $invoice = Invoice::create([
                'client_id' => $order->client_id,
                'service_id' => $order->service_id,
                'total_cents' => (int) $order->amount_cents,
                'amount_paid_cents' => 0,
                'currency' => $currency,
                'status' => InvoiceStatus::Pending->value,
                'issued_at' => now()->toDateString(),
                'due_at' => $dueAt ?: now()->addDays(15)->toDateString(),
                'notes' => 'Generada desde la orden de pago '.$order->folio.' ('.$order->concept.').',
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => (int) $item->unit_price_cents,
                    'amount_cents' => (int) $item->amount_cents,
                    'currency' => $currency,
                ]);
            }

            $this->audit->log(AuditAction::InvoiceCreated, $invoice, null, $this->snapshot($invoice));

            return $invoice;
        });
    }

    /**
     * Actualiza datos y, opcionalmente, reemplaza archivos.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data, ?UploadedFile $pdf = null, ?UploadedFile $xml = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $pdf, $xml): Invoice {
            $old = $this->snapshot($invoice);

            if ($pdf) {
                $this->deleteFile($invoice->pdf_path);
                $invoice->pdf_path = $pdf->store('invoices/'.$invoice->client_id, self::DISK);
            }
            if ($xml) {
                $this->deleteFile($invoice->xml_path);
                $invoice->xml_path = $xml->store('invoices/'.$invoice->client_id, self::DISK);
            }

            $invoice->fill([
                'folio' => $data['folio'] ?? $invoice->folio,
                'uuid' => $data['uuid'] ?? $invoice->uuid,
                'total_cents' => isset($data['total'])
                    ? Money::fromDecimal($data['total'])->amountInCents
                    : $invoice->total_cents,
                'status' => $data['status'] ?? $invoice->status->value,
                'issued_at' => $data['issued_at'] ?? $invoice->issued_at,
                'due_at' => $data['due_at'] ?? $invoice->due_at,
                'notes' => $data['notes'] ?? $invoice->notes,
            ])->save();

            $this->audit->log(AuditAction::InvoiceUpdated, $invoice, $old, $this->snapshot($invoice->refresh()));

            return $invoice;
        });
    }

    public function changeStatus(Invoice $invoice, InvoiceStatus $status): Invoice
    {
        $old = ['status' => $invoice->status->value];

        $invoice->update(['status' => $status->value]);

        $this->audit->log(AuditAction::InvoiceStatusChanged, $invoice, $old, ['status' => $status->value]);

        return $invoice;
    }

    public function delete(Invoice $invoice): void
    {
        $this->audit->log(AuditAction::InvoiceDeleted, $invoice, $this->snapshot($invoice));

        $this->deleteFile($invoice->pdf_path);
        $this->deleteFile($invoice->xml_path);

        $invoice->delete();
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Invoice $invoice): array
    {
        return [
            'client_id' => $invoice->client_id,
            'folio' => $invoice->folio,
            'uuid' => $invoice->uuid,
            'total_cents' => $invoice->total_cents,
            'status' => $invoice->status?->value,
            'issued_at' => $invoice->issued_at?->toDateString(),
            'due_at' => $invoice->due_at?->toDateString(),
        ];
    }
}
