<?php

namespace App\Integrations\Events;

/**
 * Contrato de un evento de dominio que las integraciones pueden observar.
 * Los eventos concretos viven en su propio dominio (p. ej.
 * App\Domain\Tickets\Events\TicketCreated) e implementan esta interfaz para
 * exponer un nombre estable y una carga serializable.
 *
 * El módulo de negocio solo emite el evento; no conoce a ningún proveedor.
 */
interface IntegrationEvent
{
    /** Nombre estable del evento: 'TicketCreated', 'InvoiceCreated'... */
    public function eventName(): string;

    /**
     * Datos serializables del evento para enviarlos a la cola. Deben poder
     * reconstruirse sin depender de modelos vivos (ids + campos planos).
     *
     * @return array<string, mixed>
     */
    public function integrationPayload(): array;
}
