<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\BillingCycle;
use App\Support\Money;
use Database\Factories\PlanPriceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends Model
{
    /** @use HasFactory<PlanPriceFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return PlanPriceFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function unitPrice(): Money
    {
        return new Money($this->unit_price_cents, $this->currency);
    }

    /**
     * Importe del IVA en centavos.
     */
    public function taxCents(): int
    {
        return (int) round($this->unit_price_cents * ((float) $this->tax_rate) / 100);
    }

    /**
     * Total (unitario + IVA) en centavos.
     */
    public function totalCents(): int
    {
        return $this->unit_price_cents + $this->taxCents();
    }

    public function total(): Money
    {
        return new Money($this->totalCents(), $this->currency);
    }
}
