<?php

namespace App\Domain\Billing\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Registro de pagos contra facturas. Soporta pagos parciales: acumula el monto
 * pagado y marca la factura como pagada cuando se cubre el total.
 */
class PaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Registra un pago contra una factura y actualiza el saldo/estado.
     *
     * @param  array{
     *     amount_cents:int, method:string, paid_at:string,
     *     reference?:?string, notes?:?string,
     *     payment_proof_id?:?int, recorded_by?:?int
     * }  $data
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data): Payment {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_proof_id' => $data['payment_proof_id'] ?? null,
                'amount_cents' => $data['amount_cents'],
                'currency' => $invoice->currency,
                'method' => $data['method'],
                'paid_at' => $data['paid_at'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $data['recorded_by'] ?? auth()->id(),
            ]);

            $this->refreshInvoiceState($invoice);

            $this->audit->log(AuditAction::PaymentRecorded, $payment, null, [
                'invoice_id' => $invoice->id,
                'amount_cents' => $payment->amount_cents,
                'method' => $payment->method?->value,
            ]);

            return $payment;
        });
    }

    /**
     * Recalcula el monto pagado acumulado desde los pagos y ajusta el estado
     * de la factura (Paid si el saldo llega a cero; si estaba Paid y vuelve a
     * quedar saldo, regresa a Pending).
     */
    public function refreshInvoiceState(Invoice $invoice): Invoice
    {
        $paid = (int) $invoice->payments()->sum('amount_cents');
        $invoice->amount_paid_cents = $paid;

        $oldStatus = $invoice->status;

        if ($invoice->balanceCents() === 0 && (int) $invoice->total_cents > 0) {
            $invoice->status = InvoiceStatus::Paid;
        } elseif ($oldStatus === InvoiceStatus::Paid && $invoice->balanceCents() > 0) {
            // Se revirtió/ajustó un pago: la factura ya no está saldada.
            $invoice->status = InvoiceStatus::Pending;
        }

        $invoice->save();

        if ($oldStatus !== $invoice->status) {
            $this->audit->log(AuditAction::InvoiceStatusChanged, $invoice,
                ['status' => $oldStatus?->value],
                ['status' => $invoice->status?->value],
            );
        }

        return $invoice;
    }
}
