<?php

namespace App\Domain\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Credencial cifrada de una integración. El valor se guarda cifrado at-rest y
 * nunca se expone completo: la UI muestra solo los últimos caracteres (last4).
 */
class IntegrationSecret extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'key',
        'value',
        'last4',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'value',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** Guarda el secreto cifrado y calcula el last4 para mostrarlo enmascarado. */
    public static function putFor(Integration $integration, string $key, string $plain): self
    {
        return static::updateOrCreate(
            ['integration_id' => $integration->id, 'key' => $key],
            [
                'value' => Crypt::encryptString($plain),
                'last4' => substr($plain, -4),
            ],
        );
    }

    /** Devuelve el valor descifrado (solo para uso interno de los providers). */
    public function reveal(): string
    {
        return Crypt::decryptString($this->value);
    }

    /** Representación enmascarada para la UI: ••••••1234 */
    public function masked(): string
    {
        return '••••••'.($this->last4 ?? '');
    }
}
