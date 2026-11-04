<?php

namespace App\Integrations\Registry;

/**
 * Metadatos de un proveedor disponible en el catálogo. Es información estática
 * (no vive en BD): describe qué es la integración, qué campos necesita y qué
 * eventos soporta. "Instalar" un proveedor crea una fila configurable en la
 * tabla `integrations` a partir de su manifiesto.
 */
final class IntegrationManifest
{
    /**
     * @param  list<string>  $configFields   Campos de configuración NO sensibles.
     * @param  list<string>  $secretFields   Campos de credenciales (cifrados).
     * @param  list<string>  $events         Eventos de dominio que soporta.
     * @param  class-string|null  $providerClass  Provider real (si existe).
     * @param  class-string|null  $fakeProviderClass  Provider fake (para pruebas).
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly IntegrationCapability $capability,
        public readonly string $description,
        public readonly array $configFields = [],
        public readonly array $secretFields = [],
        public readonly array $events = [],
        public readonly bool $supportsWebhooks = false,
        public readonly ?string $providerClass = null,
        public readonly ?string $fakeProviderClass = null,
    ) {}
}
