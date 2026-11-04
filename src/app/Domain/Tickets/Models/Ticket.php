<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Tickets\Enums\TicketDepartment;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return TicketFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'type',
        'client_id',
        'opened_by_user_id',
        'internal_area_id',
        'subject',
        'department',
        'priority',
        'criticality',
        'status',
        'last_reply_at',
        'first_response_at',
        'resolved_at',
        'sla_breach_alerted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => \App\Domain\Tickets\Enums\TicketType::class,
            'department' => TicketDepartment::class,
            'priority' => TicketPriority::class,
            'criticality' => \App\Domain\Tickets\Enums\TicketCriticality::class,
            'status' => TicketStatus::class,
            'last_reply_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_breach_alerted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** ¿Es un ticket interno (levantado por un empleado)? */
    public function isInternal(): bool
    {
        return $this->type === \App\Domain\Tickets\Enums\TicketType::Internal;
    }

    /** Empleado que abrió el ticket (solo tickets internos). */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'opened_by_user_id');
    }

    /** Área interna a la que va dirigido (solo tickets internos). */
    public function internalArea(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Internal\Models\InternalArea::class, 'internal_area_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function survey(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SatisfactionSurvey::class);
    }
}
