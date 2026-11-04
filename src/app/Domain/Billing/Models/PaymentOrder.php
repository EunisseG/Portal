<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'invoice_id',
        'amount_cents',
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
            'status' => PaymentOrderStatus::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
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
