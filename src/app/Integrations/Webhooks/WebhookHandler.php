<?php

namespace App\Integrations\Webhooks;

use Illuminate\Http\Request;

/**
 * Contrato de un manejador de webhooks entrantes para un proveedor concreto.
 * Cada proveedor que reciba webhooks implementa esta interfaz.
 */
interface WebhookHandler
{
    /** Clave del proveedor: coincide con {provider} en la ruta. */
    public function provider(): string;

    /** Verifica la firma del webhook con el secreto del proveedor. */
    public function verifySignature(Request $request): bool;

    /** Extrae el id de evento externo (para idempotencia). */
    public function externalEventId(Request $request): string;

    /**
     * Procesa el payload validado. Se ejecuta en cola. Debe aplicar la política
     * de conflicto (por defecto WORTEV es la fuente de verdad) y no confiar
     * ciegamente en los datos externos.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void;
}
