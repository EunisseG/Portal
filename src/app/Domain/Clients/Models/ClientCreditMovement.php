<?php

namespace App\Domain\Clients\Models;

use App\Domain\Clients\Enums\CreditMovementType;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del saldo a favor de un cliente (abono o cargo).
 */
class ClientCreditMovement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'type',
        'amount_cents',
        'currency',
        'balance_after_cents',
        'reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditMovementType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function balanceAfter(): Money
    {
        return new Money((int) ($this->balance_after_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
