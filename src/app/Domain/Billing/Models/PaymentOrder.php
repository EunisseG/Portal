<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de pago en línea (folio de cobro). Representa un intento de cobro por
 * pasarela para una factura. Es independiente del CFDI: la factura lleva el
 * sello del SAT; la orden solo rastrea el cobro (estado, referencia, caducidad).
 */
class PaymentOrder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'folio',
        'client_id',
        'service_id',
        'kind',
        'invoice_id',
        'amount_cents',
        'subtotal_cents',
        'discount_cents',
        'tax_cents',
        'tax_rate',
        'concept',
        'currency',
        'status',
        'gateway',
        'external_reference',
        'preference_id',
        'checkout_url',
        'expires_at',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'tax_rate' => 'decimal:2',
            'status' => PaymentOrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** Orden del pedido completo (alta o renovación). */
    public const KIND_SERVICE = 'service';

    /** Orden solo de complementos agregados a un pedido ya pagado. */
    public const KIND_ADDONS = 'addons';

    public function isForAddons(): bool
    {
        return $this->kind === self::KIND_ADDONS;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Services\Models\Service::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentOrderItem::class);
    }

    /** Comprobantes de pago que el cliente subió para esta orden. */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
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

    public function isPending(): bool
    {
        return $this->status === PaymentOrderStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
