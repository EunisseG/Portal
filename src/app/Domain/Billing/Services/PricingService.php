<?php

namespace App\Domain\Billing\Services;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Promotion;
use App\Domain\Services\Models\Service;

/**
 * Servicio central de cálculo de precios de un pedido (Service).
 *
 * Modelo de cálculo (todo en centavos, sin coma flotante):
 *   base       = unit_price_cents del pedido (precio del plan/servicio)
 *   addons     = suma de price_cents de los complementos activos
 *   subtotal   = base + addons
 *   discount   = descuento de la promoción aplicada sobre el subtotal
 *   taxBase    = subtotal - discount
 *   tax        = round(taxBase * tax_rate / 100)
 *   total      = taxBase + tax
 *
 * El IVA se calcula sobre la base ya con descuento (base gravable), que es el
 * criterio fiscal habitual en México.
 */
class PricingService
{
    /**
     * Calcula el desglose de un pedido a partir de sus valores actuales y sus
     * complementos. Devuelve un arreglo con las claves en centavos.
     *
     * @return array{
     *     subtotal_cents:int, discount_cents:int, tax_cents:int, total_cents:int
     * }
     */
    public function breakdownFor(Service $service): array
    {
        $serviceRate = (float) ($service->tax_rate ?? 0);

        // Cada concepto lleva su IVA: el servicio el del pedido y cada
        // complemento el suyo (o el del pedido si no tiene uno propio).
        $lines = [['cents' => (int) ($service->unit_price_cents ?? 0), 'rate' => $serviceRate]];
        foreach ($this->activeAddons($service) as $addon) {
            $lines[] = ['cents' => (int) $addon->price_cents, 'rate' => $addon->effectiveTaxRate($serviceRate)];
        }

        return $this->computeLines(
            $lines,
            $service->discount_type?->value,
            $service->discount_value !== null ? (int) $service->discount_value : null,
        );
    }

    /**
     * Cálculo con IVA por concepto. El descuento se reparte entre los
     * conceptos en proporción a su importe y el IVA se calcula por tasa sobre
     * la base ya descontada. Con una sola tasa da exactamente el mismo
     * resultado que compute().
     *
     * @param  list<array{cents:int, rate:float}>  $lines
     * @return array{subtotal_cents:int, discount_cents:int, tax_cents:int, total_cents:int}
     */
    public function computeLines(array $lines, ?string $discountType = null, ?int $discountValue = null): array
    {
        $lines = array_values(array_map(
            fn ($l) => ['cents' => max(0, (int) $l['cents']), 'rate' => (float) $l['rate']],
            $lines,
        ));

        $subtotal = array_sum(array_column($lines, 'cents'));
        $discount = $this->discountCents($subtotal, $discountType, $discountValue);

        // Base gravable por tasa (en centavos enteros, sin perder ninguno).
        $baseByRate = [];
        $allocated = 0;
        $last = count($lines) - 1;
        foreach ($lines as $i => $line) {
            $share = $subtotal > 0
                ? ($i === $last ? $discount - $allocated : intdiv($discount * $line['cents'], $subtotal))
                : 0;
            $allocated += $share;

            $key = (string) $line['rate'];
            $baseByRate[$key] = ($baseByRate[$key] ?? 0) + ($line['cents'] - $share);
        }

        $tax = 0;
        foreach ($baseByRate as $rate => $base) {
            $tax += (int) round(max(0, $base) * (float) $rate / 100);
        }

        $taxBase = max(0, $subtotal - $discount);

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'tax_cents' => $tax,
            'total_cents' => $taxBase + $tax,
        ];
    }

    /**
     * Recalcula y persiste el desglose (subtotal/discount/tax/total y
     * price_cents por compatibilidad) en el pedido. No guarda otros campos.
     */
    public function recalculate(Service $service): Service
    {
        $b = $this->breakdownFor($service);

        $service->forceFill([
            'subtotal_cents' => $b['subtotal_cents'],
            'discount_cents' => $b['discount_cents'],
            'tax_cents' => $b['tax_cents'],
            'total_cents' => $b['total_cents'],
            // price_cents se mantiene como el total contratado (compatibilidad
            // con reportes/vistas previas que lo usan).
            'price_cents' => $b['total_cents'],
        ]);

        if ($service->exists) {
            $service->save();
        }

        return $service;
    }

    /**
     * Aplica una promoción al pedido: valida vigencia, guarda promotion_id y
     * congela el tipo/valor del descuento, y recalcula. Devuelve true si se
     * aplicó, false si la promoción no es válida.
     */
    public function applyPromotion(Service $service, Promotion $promotion): bool
    {
        if (! $promotion->isValidNow()) {
            return false;
        }

        $service->promotion_id = $promotion->id;
        $service->discount_type = $promotion->discount_type->value;
        $service->discount_value = (int) $promotion->discount_value;

        $this->recalculate($service);

        return true;
    }

    /** Quita la promoción/descuento del pedido y recalcula. */
    public function removePromotion(Service $service): Service
    {
        $service->promotion_id = null;
        $service->discount_type = null;
        $service->discount_value = null;

        return $this->recalculate($service);
    }

    /**
     * Cálculo puro a partir de valores primitivos. Útil para previsualizar en
     * formularios antes de persistir.
     *
     * @return array{
     *     subtotal_cents:int, discount_cents:int, tax_cents:int, total_cents:int
     * }
     */
    public function compute(
        int $baseCents,
        int $addonsCents = 0,
        float $taxRate = 0.0,
        ?string $discountType = null,
        ?int $discountValue = null,
    ): array {
        $subtotal = max(0, $baseCents) + max(0, $addonsCents);

        $discount = $this->discountCents($subtotal, $discountType, $discountValue);

        $taxBase = max(0, $subtotal - $discount);
        $tax = (int) round($taxBase * $taxRate / 100);
        $total = $taxBase + $tax;

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'tax_cents' => $tax,
            'total_cents' => $total,
        ];
    }

    /**
     * Complementos que cuentan para el precio. Solo los ACTIVOS: los cancelados
     * y los suspendidos (p. ej. recurrentes vencidos) no se cobran.
     */
    private function activeAddons(Service $service): \Illuminate\Support\Collection
    {
        // Activos y sin los de pago único ya pagados (no se cobran dos veces).
        return $service->billableAddons();
    }

    /**
     * Descuento en centavos según tipo/valor congelados, acotado al subtotal.
     */
    private function discountCents(int $subtotalCents, ?string $discountType, ?int $discountValue): int
    {
        if ($discountType === null || $discountValue === null || $subtotalCents <= 0) {
            return 0;
        }

        $discount = $discountType === \App\Domain\Services\Enums\DiscountType::Percent->value
            ? (int) round($subtotalCents * $discountValue / 100)
            : $discountValue;

        return min(max(0, $discount), $subtotalCents);
    }
}
