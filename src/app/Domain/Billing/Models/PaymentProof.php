<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\PaymentMethod;
use App\Domain\Billing\Enums\PaymentProofStatus;
use App\Domain\Clients\Models\Client;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprobante de pago enviado por un cliente. Queda "en revisión" hasta que el
 * admin/staff lo aprueba o rechaza. Puede estar ligado a una factura.
 */
class PaymentProof extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'invoice_id',
        'file_path',
        'original_name',
        'amount_cents',
        'currency',
        'method',
        'paid_at',
        'reference',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentProofStatus::class,
            'paid_at' => 'date',
            'reviewed_at' => 'datetime',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentProofStatus::Pending;
    }
}
