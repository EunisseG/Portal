<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Clients\Models\Client;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return InvoiceFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'service_id',
        'folio',
        'uuid',
        'total_cents',
        'amount_paid_cents',
        'currency',
        'status',
        'issued_at',
        'due_at',
        'pdf_path',
        'xml_path',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'total_cents' => 'integer',
            'amount_paid_cents' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Services\Models\Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function total(): Money
    {
        return new Money((int) ($this->total_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    public function amountPaid(): Money
    {
        return new Money((int) ($this->amount_paid_cents ?? 0), $this->currency ?? Money::DEFAULT_CURRENCY);
    }

    /** Saldo pendiente (nunca negativo). */
    public function balance(): Money
    {
        return $this->total()->subtract($this->amountPaid());
    }

    public function balanceCents(): int
    {
        return max(0, (int) $this->total_cents - (int) $this->amount_paid_cents);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceCents() === 0 && (int) $this->total_cents > 0;
    }

    public function hasXml(): bool
    {
        return ! empty($this->xml_path);
    }

    /**
     * ¿La factura está timbrada ante el SAT? Se considera timbrada cuando tiene
     * folio fiscal (UUID), que es el identificador oficial del CFDI. Estas
     * facturas no deben borrarse (ni borrar al cliente dueño) para conservar el
     * histórico fiscal. (El campo "folio" es un consecutivo interno, no basta.)
     */
    public function isStamped(): bool
    {
        return ! empty($this->uuid);
    }
}
