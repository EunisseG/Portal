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
        'tax_rate',
        'currency',
        'is_active',
        'priority',
    ];

    /** Slug del tipo hosting: estos productos solo nacen de un plan. */
    public const TYPE_HOSTING = 'hosting';

    /** Slug del tipo dominio: el título del producto es la extensión (.com, .mx...). */
    public const TYPE_DOMAIN = 'domain';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'type' ("Funciona como") guarda el valor de ProductType; se deja
            // como string para tolerar valores antiguos (se leen como "Otro").
            'billing_mode' => \App\Domain\Services\Enums\BillingMode::class,
            'price_cents' => 'integer',
            // IVA del pago único (los recurrentes lo llevan por ciclo).
            'tax_rate' => 'decimal:2',
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

        return (\App\Domain\Services\Enums\ProductType::tryFrom((string) $this->type)
            ?? \App\Domain\Services\Enums\ProductType::Custom)->label();
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

    /** ¿Es una extensión de dominio (.com, .mx...)? */
    public function isDomain(): bool
    {
        return $this->type === self::TYPE_DOMAIN;
    }

    /** Extensión normalizada del producto de dominio (".com.mx"). */
    public function tld(): string
    {
        return '.'.ltrim(strtolower(trim((string) $this->name)), '.');
    }

    /** Tipo de pedido (servicio) que genera este producto. */
    public function serviceType(): \App\Domain\Services\Enums\ServiceType
    {
        return \App\Domain\Services\Enums\ServiceType::tryFrom((string) $this->type)
            ?? \App\Domain\Services\Enums\ServiceType::Other;
    }

    /**
     * Precio unitario e IVA para un ciclo: el del ciclo (recurrente) o el
     * precio plano con su IVA (pago único).
     *
     * @return array{unit_cents:int, tax_rate:float, cycle:?\App\Domain\Services\Enums\BillingCycle}
     */
    public function pricingFor(?string $cycleValue): array
    {
        if ($this->isRecurring()) {
            $this->loadMissing('prices');
            $price = $this->prices->first(fn ($p) => $p->billing_cycle->value === $cycleValue)
                ?? $this->prices->first();

            if ($price) {
                return [
                    'unit_cents' => (int) $price->unit_price_cents,
                    'tax_rate' => (float) $price->tax_rate,
                    'cycle' => $price->billing_cycle,
                ];
            }
        }

        return [
            'unit_cents' => (int) $this->price_cents,
            'tax_rate' => (float) ($this->tax_rate ?? 0),
            'cycle' => null,
        ];
    }

    /** Precio de pago único como Money. */
    public function price(): Money
    {
        return new Money((int) ($this->price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
