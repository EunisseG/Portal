<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketCriticality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SLA específico de un cliente para una criticidad. Sobrescribe al catálogo
 * global (SlaPolicy) solo para ese cliente y esa criticidad. Si no existe, el
 * ticket usa la política global.
 */
class ClientSlaPolicy extends Model
{
    protected $table = 'client_sla_policies';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'criticality',
        'first_response_minutes',
        'resolution_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'criticality' => TicketCriticality::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
