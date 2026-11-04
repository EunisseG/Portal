<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Vínculo entre una entidad de WORTEV (fuente de verdad) y su representación en
 * un sistema externo. Ej.: Ticket #1025 ↔ Monday item 458721.
 */
class IntegrationReference extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'referenceable_type',
        'referenceable_id',
        'external_id',
        'external_url',
        'sync_status',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
