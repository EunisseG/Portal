<?php

namespace App\Integrations\Contracts;

/**
 * Implementado por los providers capaces de reaccionar a eventos de dominio.
 * El Job DispatchIntegrationEvent llama a handleEvent() con el nombre del
 * evento y su payload serializado; el provider decide qué operación ejecutar
 * (crear item, notificar, etc.) y devuelve un GatewayResult.
 */
interface IntegrationDispatchable
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleEvent(string $eventName, array $payload): GatewayResult;
}
