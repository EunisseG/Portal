<?php

namespace App\Integrations\Webhooks;

/**
 * Verificación de firma HMAC para webhooks entrantes. Compara la firma enviada
 * por el proveedor con un HMAC-SHA256 del cuerpo crudo usando el secreto
 * compartido. Usa comparación en tiempo constante para evitar timing attacks.
 */
class SignatureVerifier
{
    public function verifyHmacSha256(string $rawBody, string $providedSignature, string $secret): bool
    {
        if ($providedSignature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $providedSignature);
    }
}
