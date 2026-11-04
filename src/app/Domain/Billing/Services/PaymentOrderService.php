<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestiona las órdenes de pago en línea (folios de cobro). Una orden se crea
 * contra una factura, se cobra por la pasarela y, al confirmarse por webhook,
 * registra el pago y marca la factura. Caduca a los 5 días si no se paga.
 *
 * La orden es independiente de la factura CFDI (que lleva el sello del SAT).
 */
class PaymentOrderService
{
    /** Días que una orden permanece válida antes de expirar. */
    public const EXPIRES_DAYS = 5;

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Crea una orden de pago para una factura: genera el cobro en la pasarela y
     * persiste el folio con su checkout. Cancela cualquier orden pendiente
     * previa de la misma factura (solo una orden abierta por factura).
     */
    public function create(Invoice $invoice, PaymentGateway $gateway): PaymentOrder
    {
        // Cancela órdenes pendientes previas de esta factura (se regenera una
        // nueva en cada intento de pago/renovación).
        PaymentOrder::where('invoice_id', $invoice->id)
            ->where('status', PaymentOrderStatus::Pending->value)
            ->update(['status' => PaymentOrderStatus::Cancelled->value]);

        $folio = $this->generateFolio();
        $amountCents = (int) $invoice->balanceCents();

        $result = $gateway->createCharge([
            'invoice_id' => $invoice->id,
            'amount_cents' => $amountCents,
            'amount' => round($amountCents / 100, 2), // el provider real espera pesos
            'title' => 'Pago factura '.($invoice->folio ?: '#'.$invoice->id),
            'currency' => $invoice->currency,
            'reference' => $folio, // external_reference = folio de la orden
        ]);

        if (! $result->success) {
            throw new \RuntimeException($result->message ?: 'No se pudo crear la orden de pago.');
        }

        return PaymentOrder::create([
            'folio' => $folio,
            'client_id' => $invoice->client_id,
            'invoice_id' => $invoice->id,
            'amount_cents' => $amountCents,
            'currency' => $invoice->currency,
            'status' => PaymentOrderStatus::Pending->value,
            'gateway' => 'mercadopago',
            'external_reference' => (string) ($result->data['reference'] ?? $folio),
            'preference_id' => $result->data['preference_id'] ?? null,
            'checkout_url' => $result->data['checkout_url'] ?? null,
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
            'paid_at' => null,
        ]);
    }

    /**
     * Marca una orden como pagada y registra el pago contra su factura. Es
     * idempotente: si la orden ya está pagada, no hace nada.
     */
    public function markPaid(PaymentOrder $order, ?string $gatewayPaymentId = null): void
    {
        if ($order->status === PaymentOrderStatus::Paid) {
            return; // ya conciliada
        }

        DB::transaction(function () use ($order, $gatewayPaymentId): void {
            $order->forceFill([
                'status' => PaymentOrderStatus::Paid->value,
                'paid_at' => now(),
            ])->save();

            // Registra el pago contra la factura (si la orden tiene una).
            if ($order->invoice) {
                $this->payments->record($order->invoice, [
                    'amount_cents' => (int) $order->amount_cents,
                    'method' => PaymentMethod::Card->value,
                    'paid_at' => now()->toDateString(),
                    'reference' => $order->folio.($gatewayPaymentId ? ' / '.$gatewayPaymentId : ''),
                    'notes' => 'Pago en línea ('.$order->gateway.').',
                    'recorded_by' => null, // proceso automático (webhook)
                ]);
            }
        });
    }

    /** Marca una orden como expirada (solo si sigue pendiente). */
    public function expire(PaymentOrder $order): void
    {
        if ($order->isPending()) {
            $order->update(['status' => PaymentOrderStatus::Expired->value]);
        }
    }

    /** Cancela una orden pendiente. */
    public function cancel(PaymentOrder $order): void
    {
        if ($order->isPending()) {
            $order->update(['status' => PaymentOrderStatus::Cancelled->value]);
        }
    }

    /** Genera un folio único de orden de pago (PO-YYYYMMDD-XXXXXX). */
    private function generateFolio(): string
    {
        do {
            $folio = 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (PaymentOrder::where('folio', $folio)->exists());

        return $folio;
    }
}
