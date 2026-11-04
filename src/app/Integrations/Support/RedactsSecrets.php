<?php

namespace App\Integrations\Support;

/**
 * Utilidad para enmascarar claves sensibles antes de registrarlas en logs.
 * Cualquier clave cuyo nombre sugiera una credencial se reemplaza por
 * '[REDACTED]'. Se usa antes de persistir contexto en integration_logs.
 */
trait RedactsSecrets
{
    /** @var list<string> Fragmentos de nombre que marcan un valor sensible. */
    protected array $sensitiveKeys = [
        'authorization', 'auth', 'api_key', 'apikey', 'token', 'password',
        'secret', 'client_secret', 'bearer', 'signature', 'webhook_url',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function redact(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->redact($value);

                continue;
            }

            $result[$key] = $this->isSensitive((string) $key) ? '[REDACTED]' : $value;
        }

        return $result;
    }

    protected function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->sensitiveKeys as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
