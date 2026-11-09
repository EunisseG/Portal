<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\PaymentGateway;
use App\Support\OperationMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestiona las órdenes de pago en línea (folios de cobro). Una orden nace de un
 * PEDIDO (Service): copia su cliente, su desglose (subtotal/descuento/IVA/total)
 * y sus conceptos, y se cobra por la pasarela. Al confirmarse el pago (webhook)
 * se genera la factura desde el pedido y se registra el pago. Caduca a los 5
 * días si no se paga.
 *
 * La orden es independiente de la factura CFDI (que lleva el sello del SAT): la
 * factura se genera DESPUÉS del pago, como registro pendiente de timbrar.
 */
class PaymentOrderService
{
    /** Días que una orden permanece válida antes de expirar. */
    public const EXPIRES_DAYS = 5;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
        private readonly OperationMode $mode,
    ) {}

    /**
     * Crea una orden de pago para un PEDIDO (Service): copia su desglose y sus
     * conceptos, genera el cobro en la pasarela (si hay monto y pasarela) y
     * persiste el folio con su checkout. Cancela cualquier orden pendiente
     * previa del mismo pedido (solo una orden abierta por pedido).
     *
     * Si el pedido es gratuito (total 0, p. ej. promoción al 100%) la orden se
     * crea igual pero sin checkout: no hay nada que cobrar por la pasarela.
     */
    public function createForService(Service $service, ?PaymentGateway $gateway = null): PaymentOrder
    {
        $service->loadMissing(['addons', 'plan', 'promotion']);

        // Cancela órdenes pendientes previas de este pedido.
        PaymentOrder::where('service_id', $service->id)
            ->where('status', PaymentOrderStatus::Pending->value)
            ->update(['status' => PaymentOrderStatus::Cancelled->value]);

        $folio = $this->generateFolio();
        $currency = $service->currency ?? 'MXN';
        $totalCents = (int) ($service->total_cents ?? 0);
        $concept = $service->plan?->name ?: $service->name;

        $checkout = [
            'external_reference' => $folio,
            'preference_id' => null,
            'checkout_url' => null,
        ];

        // La pasarela solo se usa con los pagos en modo AUTOMÁTICO, cuando hay
        // monto y una pasarela realmente configurada. En modo manual la orden
        // se crea sin enlace de pago (el cliente sube su comprobante); si el
        // modo cambia después, ensureCheckout() genera el enlace.
        if ($totalCents > 0 && $gateway !== null && $this->canCharge($gateway)) {
            $checkout = $this->requestCheckout($folio, $totalCents, $currency, $concept, $service->id, $gateway);
        }

        return DB::transaction(function () use ($service, $folio, $currency, $totalCents, $concept, $checkout): PaymentOrder {
            $order = PaymentOrder::create([
                'folio' => $folio,
                'client_id' => $service->client_id,
                'service_id' => $service->id,
                'invoice_id' => null,
                'amount_cents' => $totalCents,
                'subtotal_cents' => (int) ($service->subtotal_cents ?? 0),
                'discount_cents' => (int) ($service->discount_cents ?? 0),
                'tax_cents' => (int) ($service->tax_cents ?? 0),
                'tax_rate' => (float) ($service->tax_rate ?? 0),
                'concept' => $concept,
                'currency' => $currency,
                'status' => PaymentOrderStatus::Pending->value,
                'gateway' => 'mercadopago',
                'external_reference' => $checkout['external_reference'],
                'preference_id' => $checkout['preference_id'],
                'checkout_url' => $checkout['checkout_url'],
                'expires_at' => now()->addDays(self::EXPIRES_DAYS),
                'paid_at' => null,
            ]);

            $this->copyServiceLines($order, $service);

            return $order;
        });
    }

    /**
     * Copia los conceptos del pedido a las líneas de la orden: servicio base,
     * cada complemento activo y, si aplica, una línea de descuento (negativa).
     */
    private function copyServiceLines(PaymentOrder $order, Service $service): void
    {
        $currency = $order->currency;

        $baseCents = (int) ($service->unit_price_cents ?? 0);
        $order->items()->create([
            'description' => $service->plan?->name ?: $service->name,
            'quantity' => 1,
            'unit_price_cents' => $baseCents,
            'amount_cents' => $baseCents,
            'currency' => $currency,
        ]);

        foreach ($service->addons as $addon) {
            if ($addon->status !== AddonStatus::Active) {
                continue;
            }
            $order->items()->create([
                'description' => 'Complemento: '.$addon->name,
                'quantity' => 1,
                'unit_price_cents' => (int) $addon->price_cents,
                'amount_cents' => (int) $addon->price_cents,
                'currency' => $currency,
            ]);
        }

        if ((int) ($service->discount_cents ?? 0) > 0) {
            $label = $service->promotion?->name ?: 'Descuento';
            $order->items()->create([
                'description' => 'Descuento: '.$label,
                'quantity' => 1,
                'unit_price_cents' => 0,
                'amount_cents' => -1 * (int) $service->discount_cents,
                'currency' => $currency,
            ]);
        }
    }

    /**
     * Marca una orden como pagada. Si la orden no tiene factura todavía pero sí
     * un pedido, genera la factura desde el pedido (registro pendiente de
     * timbrar) y registra el pago contra ella. Es idempotente.
     */
    /**
     * @param  array{
     *     method?: string, paid_at?: string, reference?: ?string,
     *     notes?: ?string, recorded_by?: ?int, payment_proof_id?: ?int
     * }  $options  Datos del pago. Sin opciones se asume pago en línea (webhook).
     *     Un pago manual (admin o comprobante aprobado) manda su método,
     *     referencia, fecha y quién lo registró.
     */
    public function markPaid(PaymentOrder $order, ?string $gatewayPaymentId = null, array $options = []): void
    {
        if ($order->status === PaymentOrderStatus::Paid) {
            return; // ya conciliada
        }

        DB::transaction(function () use ($order, $gatewayPaymentId, $options): void {
            // Factura ya ligada (si la hubiera) o se genera desde el pedido.
            $invoice = $order->invoice;

            if (! $invoice && $order->service) {
                // Se genera ahora (tras el pago) como registro pendiente de
                // timbrar; se liga a la orden y se refresca la relación.
                $invoice = $this->invoices->createFromService($order->service);
                $order->invoice_id = $invoice->id;
                $order->setRelation('invoice', $invoice);
            }

            $order->forceFill([
                'invoice_id' => $order->invoice_id,
                'status' => PaymentOrderStatus::Paid->value,
                'paid_at' => now(),
            ])->save();

            if (! $invoice) {
                return;
            }

            // Orden sin costo (p. ej. promoción al 100%): no hay pago que
            // registrar; la factura simplemente queda como pagada.
            if ((int) $order->amount_cents <= 0) {
                $invoice->update(['status' => InvoiceStatus::Paid->value]);

                return;
            }

            // Registra el pago contra la factura. Por defecto es un pago en
            // línea (webhook); un pago manual manda sus propios datos.
            $reference = $options['reference'] ?? null;
            $this->payments->record($invoice, [
                'amount_cents' => (int) $order->amount_cents,
                'method' => $options['method'] ?? PaymentMethod::Card->value,
                'paid_at' => $options['paid_at'] ?? now()->toDateString(),
                'reference' => $order->folio.($reference ? ' / '.$reference : ($gatewayPaymentId ? ' / '.$gatewayPaymentId : '')),
                'notes' => $options['notes'] ?? 'Pago en línea ('.$order->gateway.').',
                'payment_proof_id' => $options['payment_proof_id'] ?? null,
                'recorded_by' => $options['recorded_by'] ?? null, // null = proceso automático
            ]);
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

    /**
     * Genera el enlace de pago de una orden pendiente que aún no lo tiene (p.
     * ej. se creó en modo manual y después los pagos pasaron a automático).
     * Si no aplica (modo manual, sin monto, ya tiene enlace, pasarela sin
     * configurar) devuelve la orden sin cambios.
     */
    public function ensureCheckout(PaymentOrder $order, PaymentGateway $gateway): PaymentOrder
    {
        if (! $order->isPending() || (int) $order->amount_cents <= 0 || $order->checkout_url) {
            return $order;
        }

        if (! $this->canCharge($gateway)) {
            return $order;
        }

        $checkout = $this->requestCheckout(
            $order->folio,
            (int) $order->amount_cents,
            $order->currency ?? 'MXN',
            $order->concept ?: 'Pedido',
            $order->service_id,
            $gateway,
        );

        $order->update($checkout);

        return $order->refresh();
    }

    /** ¿Se puede cobrar por la pasarela? Modo automático y pasarela configurada. */
    public function canCharge(PaymentGateway $gateway): bool
    {
        return $this->mode->paymentsAutomatic() && $this->gatewayConfigured($gateway);
    }

    /**
     * Pide a la pasarela el cobro y devuelve los datos de checkout.
     *
     * @return array{external_reference:string, preference_id:?string, checkout_url:?string}
     */
    private function requestCheckout(
        string $folio,
        int $totalCents,
        string $currency,
        string $concept,
        ?int $serviceId,
        PaymentGateway $gateway,
    ): array {
        $result = $gateway->createCharge([
            'service_id' => $serviceId,
            'amount_cents' => $totalCents,
            'amount' => round($totalCents / 100, 2), // el provider real espera pesos
            'title' => 'Pago pedido '.$concept,
            'currency' => $currency,
            'reference' => $folio, // external_reference = folio de la orden
        ]);

        if (! $result->success) {
            throw new \RuntimeException($result->message ?: 'No se pudo crear la orden de pago.');
        }

        return [
            'external_reference' => (string) ($result->data['reference'] ?? $folio),
            'preference_id' => $result->data['preference_id'] ?? null,
            'checkout_url' => $result->data['checkout_url'] ?? null,
        ];
    }

    /** ¿La pasarela está realmente configurada (no el fake sin configurar)? */
    private function gatewayConfigured(PaymentGateway $gateway): bool
    {
        return method_exists($gateway, 'isConfigured') ? (bool) $gateway->isConfigured() : true;
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
