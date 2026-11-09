<?php

namespace App\Domain\Services\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\DiscountType;
use App\Domain\Services\Enums\ServiceType;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ServiceFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'sales_rep_id',
        'server_id',
        'whm_username',
        'plan_id',
        'promotion_id',
        'type',
        'name',
        'status',
        'billing_cycle',
        'price_cents',
        'unit_price_cents',
        'tax_rate',
        'discount_type',
        'discount_value',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'starts_at',
        'expires_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'status' => ServiceStatus::class,
            'billing_cycle' => BillingCycle::class,
            'price_cents' => 'integer',
            'unit_price_cents' => 'integer',
            'tax_rate' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Ejecutivo de ventas (gestor) responsable de este servicio. */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Sales\Models\SalesRep::class, 'sales_rep_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(\App\Domain\Billing\Models\Invoice::class);
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(\App\Domain\Billing\Models\PaymentOrder::class);
    }

    /**
     * ¿El pedido está bloqueado por estar pagado? Lo está cuando su orden de
     * pago más reciente ya fue pagada: en ese caso no se edita ni se regenera
     * otra orden. Al generarse la orden de la siguiente renovación (pendiente),
     * la más reciente deja de estar pagada y el pedido vuelve a ser editable.
     */
    public function isLockedByPayment(): bool
    {
        $latest = $this->paymentOrders()->latest('id')->first();

        return $latest !== null
            && $latest->status === \App\Domain\Billing\Enums\PaymentOrderStatus::Paid;
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function price(): Money
    {
        return new Money((int) ($this->price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function unitPrice(): Money
    {
        return new Money((int) ($this->unit_price_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function total(): Money
    {
        return new Money((int) ($this->total_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function subtotal(): Money
    {
        return new Money((int) ($this->subtotal_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function discount(): Money
    {
        return new Money((int) ($this->discount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function tax(): Money
    {
        return new Money((int) ($this->tax_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function hasDiscount(): bool
    {
        return (int) ($this->discount_cents ?? 0) > 0;
    }
}
