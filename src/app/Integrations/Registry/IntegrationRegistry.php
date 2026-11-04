<?php

namespace App\Integrations\Registry;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\IntegrationProvider;
use Illuminate\Support\Collection;

/**
 * Catálogo central de integraciones. Conoce los manifiestos disponibles y
 * resuelve el provider concreto (real o fake) para una integración instalada.
 *
 * Los manifiestos se registran en código (no en BD). "Instalar" una
 * integración crea su fila configurable a partir del manifiesto.
 */
class IntegrationRegistry
{
    /** @var array<string, IntegrationManifest> */
    private array $manifests = [];

    /** @var array<string, class-string> Handlers de webhook por provider. */
    private array $webhookHandlers = [];

    /**
     * Si true, se fuerza siempre el provider fake (útil en pruebas). Por
     * defecto false: se usa el real cuando está configurado, y el fake si no.
     */
    private bool $preferFake = false;

    public function register(IntegrationManifest $manifest): void
    {
        $this->manifests[$manifest->key] = $manifest;
    }

    /**
     * Registra el handler de webhooks para un proveedor.
     *
     * @param  class-string  $handlerClass
     */
    public function registerWebhookHandler(string $provider, string $handlerClass): void
    {
        $this->webhookHandlers[$provider] = $handlerClass;
    }

    /** Resuelve el handler de webhook de un proveedor, si existe. */
    public function webhookHandler(string $provider): ?\App\Integrations\Webhooks\WebhookHandler
    {
        $class = $this->webhookHandlers[$provider] ?? null;

        return $class ? app($class) : null;
    }

    public function preferReal(): void
    {
        $this->preferFake = false;
    }

    /** Fuerza el uso de los providers fake (pruebas). */
    public function forceFake(): void
    {
        $this->preferFake = true;
    }

    public function has(string $key): bool
    {
        return isset($this->manifests[$key]);
    }

    public function manifest(string $key): ?IntegrationManifest
    {
        return $this->manifests[$key] ?? null;
    }

    /**
     * Catálogo completo de proveedores disponibles.
     *
     * @return Collection<int, IntegrationManifest>
     */
    public function catalog(): Collection
    {
        return collect(array_values($this->manifests));
    }

    /**
     * Resuelve el provider para una integración instalada.
     *
     * Prefiere el provider REAL cuando existe y está configurado (tiene sus
     * credenciales); si no, usa el fake. Así cada integración pasa a real de
     * forma automática al configurar sus credenciales, sin un interruptor
     * global. El flag preferFake fuerza el fake (útil en pruebas).
     */
    public function resolve(Integration $integration): ?IntegrationProvider
    {
        $manifest = $this->manifest($integration->key);

        if (! $manifest) {
            return null;
        }

        // Intentar el real si hay clase y no estamos forzando fake.
        if (! $this->preferFake && $manifest->providerClass) {
            $real = app()->makeWith($manifest->providerClass, ['integration' => $integration]);

            if ($real->isConfigured()) {
                return $real;
            }
        }

        $fake = $manifest->fakeProviderClass ?? $manifest->providerClass;

        if (! $fake) {
            return null;
        }

        return app()->makeWith($fake, ['integration' => $integration]);
    }
}
