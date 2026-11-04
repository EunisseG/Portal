<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Enums\BillingCycle;
use App\Support\Money;
use Database\Factories\AddonFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends Model
{
    /** @use HasFactory<AddonFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return AddonFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'catalog_item_id',
        'is_domain',
        'name',
        'status',
        'price_cents',
        'billing_cycle',
        'currency',
        'expires_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_domain' => 'boolean',
            'status' => AddonStatus::class,
            'price_cents' => 'integer',
            'billing_cycle' => BillingCycle::class,
            'expires_at' => 'date',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AddonCatalogItem::class, 'catalog_item_id');
    }

    public function price(): Money
    {
        return new Money((int) ($this->price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    /** ¿El complemento se cobra por ciclo (recurrente)? */
    public function isRecurring(): bool
    {
        return $this->billing_cycle !== null;
    }

    /** ¿Ya pasó su fecha de vencimiento? (solo aplica si tiene expires_at). */
    public function isExpired(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->startOfDay()->isBefore(now()->startOfDay());
    }

    /**
     * Días hasta el vencimiento (negativo si ya venció, null si no tiene fecha).
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }

    /**
     * Avanza el vencimiento un ciclo (según su billing_cycle) y reactiva el
     * complemento. La nueva fecha parte de la anterior si sigue vigente, o de
     * hoy si ya había vencido, para no acumular periodos perdidos.
     * Devuelve false si el complemento no es recurrente.
     */
    public function renew(): bool
    {
        if (! $this->isRecurring()) {
            return false;
        }

        $months = $this->billing_cycle->months();
        $base = $this->expires_at && $this->expires_at->startOfDay()->isAfter(now()->startOfDay())
            ? $this->expires_at->copy()
            : now();

        $this->forceFill([
            'expires_at' => $base->addMonths($months)->toDateString(),
            'status' => AddonStatus::Active->value,
        ])->save();

        return true;
    }
}
