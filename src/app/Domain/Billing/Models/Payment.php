<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado contra una factura. Una factura puede tener varios pagos
 * (parciales) hasta cubrir su total.
 */
class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'payment_proof_id',
        'amount_cents',
        'currency',
        'method',
        'paid_at',
        'reference',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(PaymentProof::class, 'payment_proof_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
