<?php

namespace App\Domain\Clients\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Domain\Tickets\Models\Ticket;
use App\Models\User;
use App\Support\Money;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ClientFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'company',
        'phone',
        'whatsapp',
        'tax_id',
        'billing_address',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'billing_contact',
        'credit_balance_cents',
        'credit_currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'credit_balance_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ejecutivos de ventas que atienden a este cliente, derivados de sus
     * servicios (el ejecutivo ya no se liga al cliente sino a cada servicio).
     * Un cliente puede tener varios si sus servicios los lleva distinta gente.
     *
     * @return \Illuminate\Support\Collection<int, \App\Domain\Sales\Models\SalesRep>
     */
    public function salesReps(): \Illuminate\Support\Collection
    {
        return $this->services()
            ->with('salesRep')
            ->get()
            ->pluck('salesRep')
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Sub-usuarios ligados a esta cuenta de cliente (no incluye al titular). */
    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'parent_client_id');
    }

    public function creditMovements(): HasMany
    {
        return $this->hasMany(ClientCreditMovement::class)->orderByDesc('id');
    }

    /** Saldo a favor del cliente como Money. */
    public function creditBalance(): Money
    {
        return new Money((int) ($this->credit_balance_cents ?? 0), $this->credit_currency ?? Money::DEFAULT_CURRENCY);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * ¿El cliente tiene al menos una factura timbrada (CFDI con UUID o folio)?
     * Si es así, no se le puede eliminar: hay que conservar el histórico fiscal.
     */
    public function hasStampedInvoices(): bool
    {
        return $this->invoices()
            ->whereNotNull('uuid')
            ->where('uuid', '!=', '')
            ->exists();
    }
}
