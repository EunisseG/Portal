<?php

namespace App\Integrations\Support;

use JsonSerializable;
use SensitiveParameter;

/**
 * Value object que envuelve un secreto (API key, token, contraseña). Su
 * propósito es evitar fugas: al serializar, convertir a string o volcar el
 * objeto, NUNCA expone el valor real. El valor solo se obtiene explícitamente
 * con reveal(), pensado para el momento justo de construir la petición HTTP.
 */
final class SecretString implements JsonSerializable
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $value,
    ) {}

    /** Devuelve el valor real. Úsese solo al construir la petición saliente. */
    public function reveal(): string
    {
        return $this->value;
    }

    /** Representación enmascarada segura para UI/logs: ••••••1234 */
    public function masked(): string
    {
        return '••••••'.substr($this->value, -4);
    }

    public function __toString(): string
    {
        return $this->masked();
    }

    /** Oculta el valor en var_dump/dd. */
    public function __debugInfo(): array
    {
        return ['value' => '[REDACTED]'];
    }

    /** Oculta el valor al serializar a JSON. */
    public function jsonSerialize(): string
    {
        return $this->masked();
    }
}
