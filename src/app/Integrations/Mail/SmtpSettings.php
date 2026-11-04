<?php

namespace App\Integrations\Mail;

use App\Domain\Notifications\Models\Setting;

/**
 * Lee la configuración SMTP guardada en el almacén de settings. Centraliza las
 * claves para no repetirlas por el código.
 */
class SmtpSettings
{
    public const KEY_HOST = 'smtp.host';
    public const KEY_PORT = 'smtp.port';
    public const KEY_USERNAME = 'smtp.username';
    public const KEY_PASSWORD = 'smtp.password';
    public const KEY_ENCRYPTION = 'smtp.encryption';
    public const KEY_FROM_ADDRESS = 'smtp.from_address';
    public const KEY_FROM_NAME = 'smtp.from_name';

    public function host(): ?string
    {
        return Setting::get(self::KEY_HOST) ?: null;
    }

    public function port(): int
    {
        return (int) (Setting::get(self::KEY_PORT) ?: 587);
    }

    public function username(): ?string
    {
        return Setting::get(self::KEY_USERNAME) ?: null;
    }

    public function password(): ?string
    {
        return Setting::get(self::KEY_PASSWORD) ?: null;
    }

    /** null | 'tls' | 'ssl' */
    public function encryption(): ?string
    {
        $value = Setting::get(self::KEY_ENCRYPTION);

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }

    public function fromAddress(): string
    {
        return Setting::get(self::KEY_FROM_ADDRESS) ?: 'no-reply@wortev.test';
    }

    public function fromName(): string
    {
        return Setting::get(self::KEY_FROM_NAME) ?: 'Portal WORTEV';
    }

    /** ¿Hay configuración mínima para enviar por SMTP? (al menos host). */
    public function isConfigured(): bool
    {
        return ! empty($this->host());
    }
}
