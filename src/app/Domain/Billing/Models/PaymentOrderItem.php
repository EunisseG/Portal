<?php

namespace App\Domain\Billing\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea (concepto) de una orden de pago. Se copia del pedido al crear la orden.
 * El importe puede ser negativo cuando la línea representa un descuento.
 */
class PaymentOrderItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_order_id',
        'addon_id',
        'description',
        'quantity',
        'unit_price_cents',
        'amount_cents',
        'tax_rate',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'amount_cents' => 'integer',
            'tax_rate' => 'decimal:2',
        ];
    }

    /** Complemento que cubre esta línea (si aplica). */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Services\Models\Addon::class);
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    /** Importe de la línea como Money (valor absoluto; el signo lo indica isDiscount). */
    public function amount(): Money
    {
        return new Money(abs((int) ($this->amount_cents ?? 0)), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function isDiscount(): bool
    {
        return (int) $this->amount_cents < 0;
    }
}
