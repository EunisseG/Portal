<?php

namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un intento de inicio de sesión fallido. Se llena escuchando el
 * evento Illuminate\Auth\Events\Failed. Solo para consulta administrativa.
 */
class FailedLoginAttempt extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'attempted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }
}
