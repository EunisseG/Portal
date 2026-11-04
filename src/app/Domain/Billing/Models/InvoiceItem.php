<?php

namespace App\Domain\Billing\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una factura. El importe (amount_cents) puede ser negativo cuando la
 * línea representa un descuento.
 */
class InvoiceItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_cents',
        'amount_cents',
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
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Importe de la línea como Money (usa el valor absoluto; el signo lo indica isDiscount). */
    public function amount(): Money
    {
        return new Money(abs((int) ($this->amount_cents ?? 0)), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function isDiscount(): bool
    {
        return (int) $this->amount_cents < 0;
    }
}
