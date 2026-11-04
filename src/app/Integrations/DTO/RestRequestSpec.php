<?php

namespace App\Integrations\DTO;

use App\Integrations\Support\SecretString;
use InvalidArgumentException;

/**
 * Especificación inmutable y validada de una petición HTTP para la API
 * personalizada. Encapsula todo lo necesario para ejecutar una solicitud REST
 * controlada, sin permitir ejecución de código arbitrario.
 */
final class RestRequestSpec
{
    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public const AUTH_NONE = 'none';
    public const AUTH_API_KEY = 'api_key';
    public const AUTH_BEARER = 'bearer';
    public const AUTH_BASIC = 'basic';
    public const AUTH_OAUTH2 = 'oauth2';

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $auth  Datos de autenticación (secretos como SecretString).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $baseUrl,
        public readonly string $endpoint = '',
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly string $authType = self::AUTH_NONE,
        public readonly array $auth = [],
        public readonly int $timeout = 15,
        public readonly int $retries = 0,
        public readonly string $responseFormat = 'json', // json | text
    ) {
        $method = strtoupper($method);

        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException("Método HTTP no permitido: {$method}");
        }

        if ($timeout < 1 || $timeout > 120) {
            throw new InvalidArgumentException('El timeout debe estar entre 1 y 120 segundos.');
        }

        if ($retries < 0 || $retries > 5) {
            throw new InvalidArgumentException('Los reintentos deben estar entre 0 y 5.');
        }
    }

    /** URL completa (base + endpoint), normalizando las barras. */
    public function url(): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($this->endpoint, '/');
    }

    public function methodUpper(): string
    {
        return strtoupper($this->method);
    }

    /** Devuelve el secreto de auth indicado, si existe. */
    public function authSecret(string $key): ?SecretString
    {
        $value = $this->auth[$key] ?? null;

        return $value instanceof SecretString ? $value : null;
    }
}
