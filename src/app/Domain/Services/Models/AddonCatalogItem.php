<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\BillingMode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem de catálogo de complementos: una "plantilla" reutilizable que se puede
 * agregar a cualquier pedido (Service). Al agregarlo, se crea un Addon con el
 * precio congelado.
 */
class AddonCatalogItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'billing_mode',
        'price_cents',
        'tax_rate',
        'currency',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_mode' => BillingMode::class,
            'price_cents' => 'integer',
            // IVA del pago único (los recurrentes lo llevan por ciclo).
            'tax_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Precio (centavos), IVA y ciclo del complemento para un ciclo dado: el del
     * ciclo si es recurrente (o el primero disponible), o el precio plano con
     * su IVA si es de pago único.
     *
     * @return array{cents:int, tax_rate:float, cycle:?\App\Domain\Services\Enums\BillingCycle}
     */
    public function pricingFor(?string $cycleValue): array
    {
        if ($this->isRecurring()) {
            $this->loadMissing('prices');
            $price = $this->prices->first(fn ($p) => $p->billing_cycle->value === $cycleValue)
                ?? $this->prices->first();

            if ($price) {
                return [
                    'cents' => (int) $price->unit_price_cents,
                    'tax_rate' => (float) $price->tax_rate,
                    'cycle' => $price->billing_cycle,
                ];
            }
        }

        return [
            'cents' => (int) $this->price_cents,
            'tax_rate' => (float) ($this->tax_rate ?? 16),
            'cycle' => null,
        ];
    }

    /** Precios por ciclo (solo relevantes si el modo es recurrente). */
    public function prices(): HasMany
    {
        return $this->hasMany(AddonPrice::class);
    }

    public function isRecurring(): bool
    {
        return $this->billing_mode === BillingMode::Recurring;
    }

    public function isOneTime(): bool
    {
        return ! $this->isRecurring();
    }

    public function price(): Money
    {
        return new Money((int) ($this->price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
