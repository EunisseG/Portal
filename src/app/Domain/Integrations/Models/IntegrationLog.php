<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una operación de integración (saliente o entrante). NUNCA
 * contiene secretos: solo metadata (evento, estado, http_status, duración,
 * error legible y referencias interna/externa).
 */
class IntegrationLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'event',
        'direction',
        'success',
        'http_status',
        'duration_ms',
        'message',
        'internal_reference',
        'external_reference',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
