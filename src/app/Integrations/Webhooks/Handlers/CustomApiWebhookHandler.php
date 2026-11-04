<?php

namespace App\Integrations\Webhooks\Handlers;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Webhooks\SignatureVerifier;
use App\Integrations\Webhooks\WebhookHandler;
use Illuminate\Http\Request;

/**
 * Handler de webhooks para la API personalizada. Verifica la firma con el
 * secreto 'webhook_secret' guardado (cifrado) en la integración, mediante
 * HMAC-SHA256 sobre el cuerpo crudo (header X-Signature).
 *
 * El procesamiento es un punto de extensión: por defecto no altera datos de
 * WORTEV (que sigue siendo la fuente de verdad).
 */
class CustomApiWebhookHandler implements WebhookHandler
{
    public function __construct(private readonly SignatureVerifier $verifier) {}

    public function provider(): string
    {
        return 'custom_api';
    }

    public function verifySignature(Request $request): bool
    {
        $integration = Integration::where('key', 'custom_api')->first();
        $secret = $integration?->secrets()->where('key', 'webhook_secret')->first();

        if (! $secret) {
            return false;
        }

        return $this->verifier->verifyHmacSha256(
            $request->getContent() ?: '',
            (string) $request->header('X-Signature', ''),
            $secret->reveal(),
        );
    }

    public function externalEventId(Request $request): string
    {
        return (string) $request->input('event_id', $request->header('X-Event-Id', ''));
    }

    public function process(array $payload): void
    {
        // Punto de extensión. Por defecto no modifica datos maestros de WORTEV.
        // Un proveedor concreto aplicaría aquí su política de conflicto.
    }
}
