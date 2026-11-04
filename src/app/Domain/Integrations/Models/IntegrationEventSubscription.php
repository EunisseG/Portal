<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción de una integración a un evento de dominio de WORTEV. Tabla
 * `integration_events`.
 */
class IntegrationEventSubscription extends Model
{
    protected $table = 'integration_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'event_name',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
