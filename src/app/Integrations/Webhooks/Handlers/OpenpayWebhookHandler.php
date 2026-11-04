<?php

namespace App\Integrations\Webhooks\Handlers;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Webhooks\WebhookHandler;
use Illuminate\Http\Request;

/**
 * Handler de webhooks de Openpay.
 *
 * Openpay no firma con HMAC: protege el endpoint con autenticación HTTP Basic
 * (usuario y contraseña que se configuran junto al webhook en su panel). Aquí
 * verificamos esas credenciales Basic contra el secreto 'webhook_secret'
 * guardado cifrado en formato "usuario:contraseña".
 *
 * Openpay también envía un evento de verificación ('verification') con un
 * código; el registro del evento queda en el buzón para consulta.
 */
class OpenpayWebhookHandler implements WebhookHandler
{
    public function provider(): string
    {
        return 'openpay';
    }

    public function verifySignature(Request $request): bool
    {
        $expected = $this->secret();

        if ($expected === null || ! str_contains($expected, ':')) {
            return false;
        }

        $provided = $this->basicCredentials($request);

        if ($provided === null) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    public function externalEventId(Request $request): string
    {
        // Openpay envía 'id' del evento (o 'verification_code' en el alta).
        return (string) $request->input('id', $request->input('verification_code', ''));
    }

    public function process(array $payload): void
    {
        // Punto de extensión: conciliar el cargo (charge.succeeded, etc.).
        // Por defecto no muta datos maestros de WORTEV.
    }

    /** Devuelve "usuario:contraseña" del header Authorization Basic, o null. */
    private function basicCredentials(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (! str_starts_with($header, 'Basic ')) {
            return null;
        }

        $decoded = base64_decode(substr($header, 6), true);

        return $decoded === false ? null : $decoded;
    }

    private function secret(): ?string
    {
        $integration = Integration::where('key', 'openpay')->first();

        return $integration?->secrets()->where('key', 'webhook_secret')->first()?->reveal();
    }
}
