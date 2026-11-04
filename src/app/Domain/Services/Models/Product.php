<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\BillingMode;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto del catálogo (ej. "Respaldo diario", "Hosting Pro"). Es un catálogo
 * editable, distinto de los Planes. Reutilizable al crear servicios.
 */
class Product extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'plan_id',
        'name',
        'type',
        'billing_mode',
        'category_id',
        'description',
        'price_cents',
        'currency',
        'is_active',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'type' es un slug libre del catálogo de tipos (ProductTypeItem),
            // ya no un enum fijo, para permitir tipos personalizados.
            'billing_mode' => \App\Domain\Services\Enums\BillingMode::class,
            'price_cents' => 'integer',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /** Etiqueta legible del tipo, resuelta desde el catálogo de tipos. */
    public function typeLabel(): string
    {
        if (empty($this->type)) {
            return '—';
        }

        $item = ProductTypeItem::where('slug', $this->type)->first();

        return $item?->label ?? ucfirst((string) $this->type);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** Plan del que proviene este producto (si es un producto espejo). */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** ¿Este producto es el espejo automático de un plan? */
    public function isFromPlan(): bool
    {
        return $this->plan_id !== null;
    }

    /** Precios por ciclo (solo para productos recurrentes). */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function isRecurring(): bool
    {
        return $this->billing_mode === BillingMode::Recurring;
    }

    public function isOneTime(): bool
    {
        return $this->billing_mode === BillingMode::OneTime;
    }

    /** Precio de pago único como Money. */
    public function price(): Money
    {
        return new Money((int) ($this->price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
