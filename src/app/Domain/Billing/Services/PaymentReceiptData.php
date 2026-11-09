<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PaymentOrder;
use App\Support\Branding;
use App\Support\CompanyProfile;
use App\Support\Money;

/**
 * Arma los datos de un recibo de pago (estilo recibo) a partir de una ORDEN DE
 * PAGO (PaymentOrder). Centraliza el desglose para que la pantalla web y el PDF
 * descargable muestren exactamente lo mismo. El desglose (subtotal/IVA/total)
 * viene congelado en la orden (copiado del pedido al crearla).
 */
class PaymentReceiptData
{
    public function __construct(
        private readonly CompanyProfile $company,
        private readonly Branding $branding,
    ) {}

    /**
     * @return array{
     *     order: PaymentOrder,
     *     receiptNumber: string,
     *     brandName: string,
     *     logoUrl: ?string,
     *     clientName: string,
     *     clientAddress: string,
     *     company: CompanyProfile,
     *     items: \Illuminate\Support\Collection,
     *     concept: string,
     *     subtotal: Money,
     *     discount: Money,
     *     tax: Money,
     *     taxRatePercent: string,
     *     total: Money,
     * }
     */
    public function build(PaymentOrder $order): array
    {
        $order->loadMissing(['items', 'client.user', 'service']);

        $currency = $order->currency ?? Money::DEFAULT_CURRENCY;
        $totalCents = (int) $order->amount_cents;

        // Desglose congelado en la orden (copiado del pedido, cuyo IVA viene de
        // cada producto). Si una orden no lo trae, el subtotal es el total y
        // no se inventa ningún IVA.
        $taxCents = (int) $order->tax_cents;
        $subtotalCents = (int) $order->subtotal_cents;
        if ($subtotalCents === 0 && $taxCents === 0) {
            $subtotalCents = $totalCents;
        }

        // Tasa de IVA tal como se guardó en la orden (16, 8.5, 0…).
        $taxRatePercent = rtrim(rtrim(number_format((float) ($order->tax_rate ?? 0), 2, '.', ''), '0'), '.');

        $client = $order->client;
        $clientName = $client?->user?->name ?: ($client?->company ?: 'Cliente');

        $clientAddress = $client?->billing_address
            ?: collect([
                $client?->address_line1,
                $client?->address_line2,
                $client?->city,
                $client?->state,
                $client?->postal_code,
            ])->filter()->implode(', ');

        return [
            'order' => $order,
            'receiptNumber' => $order->folio,
            'brandName' => $this->branding->name(),
            'logoUrl' => $this->branding->logoUrl(),
            'clientName' => $clientName,
            'clientAddress' => (string) $clientAddress,
            'company' => $this->company,
            'items' => $order->items,
            'concept' => $order->concept ?: 'Pedido',
            'subtotal' => new Money($subtotalCents, $currency),
            'discount' => new Money((int) $order->discount_cents, $currency),
            'tax' => new Money($taxCents, $currency),
            'taxRatePercent' => $taxRatePercent,
            'total' => new Money($totalCents, $currency),
        ];
    }
}
