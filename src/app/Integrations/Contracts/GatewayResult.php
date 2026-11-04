<?php

namespace App\Integrations\Contracts;

/**
 * Resultado genérico e inmutable devuelto por los adaptadores de integración.
 * Evita acoplar el dominio a estructuras de respuesta de proveedores concretos.
 */
final class GatewayResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message = '',
        public readonly array $data = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(string $message = '', array $data = []): self
    {
        return new self(true, $message, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }
}
