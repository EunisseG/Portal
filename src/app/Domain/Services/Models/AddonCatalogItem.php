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
            'is_active' => 'boolean',
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
