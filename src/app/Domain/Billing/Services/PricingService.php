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
        $baseCents = (int) ($service->unit_price_cents ?? 0);
        $addonsCents = $this->addonsCents($service);

        return $this->compute(
            baseCents: $baseCents,
            addonsCents: $addonsCents,
            taxRate: (float) ($service->tax_rate ?? 0),
            discountType: $service->discount_type?->value,
            discountValue: $service->discount_value !== null ? (int) $service->discount_value : null,
        );
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

    /** Suma de los complementos que cuentan para el precio (no cancelados). */
    private function addonsCents(Service $service): int
    {
        $addons = $service->relationLoaded('addons')
            ? $service->addons
            : $service->addons()->get();

        // Solo cuentan los complementos ACTIVOS: los cancelados y los
        // suspendidos (p. ej. recurrentes vencidos) no se cobran.
        return (int) $addons
            ->filter(fn ($addon) => $addon->status === AddonStatus::Active)
            ->sum('price_cents');
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
