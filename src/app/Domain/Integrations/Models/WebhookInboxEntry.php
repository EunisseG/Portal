<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Entrada del buzón de webhooks. Garantiza idempotencia: la restricción única
 * (provider, external_event_id) evita reprocesar un mismo evento externo.
 */
class WebhookInboxEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'webhook_inbox';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'external_event_id',
        'signature_valid',
        'processed_at',
        'payload_hash',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
