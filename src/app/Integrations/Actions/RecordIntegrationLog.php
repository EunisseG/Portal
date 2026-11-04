<?php

namespace App\Integrations\Actions;

use App\Domain\Integrations\Models\IntegrationLog;

/**
 * Registro uniforme de operaciones de integración. Punto único de escritura en
 * `integration_logs`. Nunca debe recibir secretos: el llamador pasa solo
 * metadata y un mensaje legible.
 */
class RecordIntegrationLog
{
    /**
     * @param  array{
     *     integration_id?: int|null, direction?: string, success?: bool,
     *     http_status?: int|null, duration_ms?: int|null, message?: string|null,
     *     internal_reference?: string|null, external_reference?: string|null
     * }  $attributes
     */
    public function record(string $event, array $attributes = []): IntegrationLog
    {
        return IntegrationLog::create([
            'integration_id' => $attributes['integration_id'] ?? null,
            'event' => $event,
            'direction' => $attributes['direction'] ?? 'outbound',
            'success' => $attributes['success'] ?? true,
            'http_status' => $attributes['http_status'] ?? null,
            'duration_ms' => $attributes['duration_ms'] ?? null,
            'message' => $attributes['message'] ?? null,
            'internal_reference' => $attributes['internal_reference'] ?? null,
            'external_reference' => $attributes['external_reference'] ?? null,
            'created_at' => now(),
        ]);
    }
}
