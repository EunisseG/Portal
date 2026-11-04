<?php

namespace App\Domain\Tickets\Models;

use App\Domain\Tickets\Enums\TicketCriticality;
use Illuminate\Database\Eloquent\Model;

/**
 * Política de SLA para una criticidad: cuántos minutos objetivo hay para la
 * primera respuesta y para la resolución de un ticket de ese nivel.
 */
class SlaPolicy extends Model
{
    protected $table = 'sla_policies';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
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
            'type' => \App\Domain\Tickets\Enums\TicketType::class,
            'criticality' => TicketCriticality::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }

    /**
     * Devuelve la política de una criticidad para un tipo de ticket
     * ('client' | 'internal'; por defecto cliente). Null si no existe.
     */
    public static function forCriticality(TicketCriticality $criticality, \App\Domain\Tickets\Enums\TicketType $type = \App\Domain\Tickets\Enums\TicketType::Client): ?self
    {
        return static::query()
            ->where('type', $type->value)
            ->where('criticality', $criticality->value)
            ->first();
    }
}
