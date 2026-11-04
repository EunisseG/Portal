<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\BillingCycle;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de un complemento de catálogo recurrente para un ciclo de facturación
 * concreto. Mismo esquema que ProductPrice.
 */
class AddonPrice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'addon_catalog_item_id',
        'billing_cycle',
        'unit_price_cents',
        'tax_rate',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'unit_price_cents' => 'integer',
            'tax_rate' => 'decimal:2',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AddonCatalogItem::class, 'addon_catalog_item_id');
    }

    public function unitPrice(): Money
    {
        return new Money((int) $this->unit_price_cents, $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function taxCents(): int
    {
        return (int) round($this->unit_price_cents * ((float) $this->tax_rate) / 100);
    }

    public function totalCents(): int
    {
        return $this->unit_price_cents + $this->taxCents();
    }

    public function total(): Money
    {
        return new Money($this->totalCents(), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
