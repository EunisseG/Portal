<?php

namespace App\Domain\Services\Services;

use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\BillingMode;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Models\Product;

/**
 * Mantiene un "producto espejo" por cada plan: al guardar un plan se crea o
 * actualiza su Product correspondiente (mismo nombre y tipo, precio tomado del
 * ciclo más común). Así los planes se ven en el catálogo de productos sin
 * capturarlos dos veces.
 */
class PlanProductSync
{
    /**
     * Orden de preferencia del ciclo "más común" para tomar el precio del
     * producto espejo: mensual primero.
     *
     * @var list<BillingCycle>
     */
    private const CYCLE_PREFERENCE = [
        BillingCycle::Monthly,
        BillingCycle::Quarterly,
        BillingCycle::SemiAnnual,
        BillingCycle::Annual,
    ];

    /** Crea o actualiza el producto espejo del plan (recurrente, con sus ciclos). */
    public function sync(Plan $plan): Product
    {
        $plan->loadMissing('prices');

        $price = $this->preferredPrice($plan);

        /** @var Product $product */
        $product = Product::firstOrNew(['plan_id' => $plan->id]);

        $product->fill([
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'type' => $this->mapType($plan->type),
            // La categoría del plan define en qué categoría se ofrece al pedir.
            'category_id' => $plan->category_id,
            // El espejo de un plan siempre es recurrente.
            'billing_mode' => BillingMode::Recurring->value,
            'description' => $plan->description,
            // price_cents se mantiene con el ciclo más común (compatibilidad).
            'price_cents' => $price?->unit_price_cents ?? 0,
            'currency' => $price?->currency ?? 'MXN',
            'is_active' => (bool) $plan->is_active,
        ]);

        // Prioridad solo al crear (no pisa la que el admin haya ordenado).
        if (! $product->exists) {
            $product->priority = (int) (Product::max('priority') ?? 0) + 1;
        }

        $product->save();

        // Copia los precios por ciclo del plan al producto (reemplazo completo).
        $product->prices()->delete();
        foreach ($plan->prices as $planPrice) {
            $product->prices()->create([
                'billing_cycle' => $planPrice->billing_cycle->value,
                'unit_price_cents' => $planPrice->unit_price_cents,
                'tax_rate' => $planPrice->tax_rate,
                'currency' => $planPrice->currency,
            ]);
        }

        return $product;
    }

    /** Elimina el producto espejo del plan (al borrar el plan). */
    public function remove(Plan $plan): void
    {
        // product_prices se borra en cascada por la FK.
        Product::where('plan_id', $plan->id)->delete();
    }

    /** Precio del ciclo preferido disponible en el plan. */
    private function preferredPrice(Plan $plan)
    {
        foreach (self::CYCLE_PREFERENCE as $cycle) {
            $price = $plan->prices->firstWhere('billing_cycle', $cycle);
            if ($price) {
                return $price;
            }
        }

        return $plan->prices->first();
    }

    /** Mapea el tipo de servicio del plan al slug de tipo de producto. */
    private function mapType(?ServiceType $type): string
    {
        return match ($type) {
            ServiceType::Hosting => 'hosting',
            ServiceType::Email => 'email',
            ServiceType::License => 'license',
            default => 'custom',
        };
    }
}
