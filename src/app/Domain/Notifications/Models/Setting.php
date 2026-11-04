<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Almacén clave/valor para configuración de la aplicación (p. ej. SMTP,
 * WhatsApp). Los valores marcados como secretos se guardan cifrados; nunca
 * se almacenan credenciales en texto plano ni en el código.
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'is_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return $setting->is_encrypted && $setting->value !== null
            ? Crypt::decryptString($setting->value)
            : $setting->value;
    }

    public static function put(string $key, ?string $value, bool $encrypted = false): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypted && $value !== null ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $encrypted,
            ],
        );
    }
}
