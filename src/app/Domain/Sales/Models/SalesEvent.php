<?php

namespace App\Domain\Sales\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Sales\Enums\SalesEventType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Evento comercial (venta nueva o renovación) ligado a un cliente y a su
 * ejecutivo de ventas. El sales_rep_id se CONGELA al momento del evento: si
 * luego el cliente se reasigna a otro ejecutivo, el histórico no cambia.
 *
 * El "subject" es polimórfico: el servicio, dominio o complemento al que
 * corresponde el evento.
 */
class SalesEvent extends Model
{
    protected $table = 'sales_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'client_id',
        'sales_rep_id',
        'subject_type',
        'subject_id',
        'amount_cents',
        'currency',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SalesEventType::class,
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function amount(): Money
    {
        return new Money((int) ($this->amount_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }
}
