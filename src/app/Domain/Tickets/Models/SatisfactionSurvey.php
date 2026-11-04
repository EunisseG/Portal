<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Clients\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encuesta de satisfacción (CSAT) asociada a un ticket. Se genera vacía al
 * cerrar el ticket y el cliente la responde con una puntuación de 1 a 5 y un
 * comentario opcional.
 */
class SatisfactionSurvey extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'type',
        'client_id',
        'user_id',
        'score',
        'comment',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => \App\Domain\Tickets\Enums\TicketType::class,
            'score' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Empleado que responde la encuesta (solo encuestas de tickets internos). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /** Respuestas por pregunta (encuesta configurable). */
    public function answers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }

    public function isAnswered(): bool
    {
        return $this->responded_at !== null;
    }
}
