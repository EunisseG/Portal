<?php

namespace App\Integrations\Providers;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationProvider;
use App\Integrations\Registry\IntegrationCapability;

/**
 * Provider fake genérico para desarrollo y pruebas. No realiza conexiones
 * reales: reporta "configurado" si la integración tiene su config mínima y
 * simula una prueba de conexión exitosa. Sirve como fakeProviderClass por
 * defecto de los manifiestos que aún no tienen un provider propio.
 */
class FakeGenericProvider implements IntegrationProvider
{
    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return $this->integration->key;
    }

    public function capability(): IntegrationCapability
    {
        return $this->integration->capability;
    }

    public function isConfigured(): bool
    {
        // En modo fake basta con estar habilitada para considerarla usable.
        return $this->integration->is_enabled;
    }

    public function testConnection(): GatewayResult
    {
        return GatewayResult::ok('Conexión simulada correcta (fake).', [
            'provider' => $this->integration->key,
        ]);
    }
}
