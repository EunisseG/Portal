<?php

namespace App\Integrations\Contracts;

use App\Integrations\Registry\IntegrationCapability;

/**
 * Contrato base de todo proveedor de integración. Cada capacidad (correo,
 * tickets, hosting...) extiende este contrato con sus operaciones concretas.
 *
 * Los proveedores no conocen el dominio de negocio: reciben DTOs y devuelven
 * un GatewayResult uniforme.
 */
interface IntegrationProvider
{
    /** Clave única del proveedor: 'monday', 'smtp', 'custom_api'... */
    public function key(): string;

    /** Capacidad que cubre este proveedor. */
    public function capability(): IntegrationCapability;

    /** ¿Tiene la configuración mínima para operar? */
    public function isConfigured(): bool;

    /** Prueba de conexión para el panel administrativo. */
    public function testConnection(): GatewayResult;
}
