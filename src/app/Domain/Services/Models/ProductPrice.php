<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\BillingCycle;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de un producto recurrente para un ciclo de facturación concreto.
 * Mismo esquema que PlanPrice.
 */
class ProductPrice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
