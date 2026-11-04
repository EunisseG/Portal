<?php

namespace App\Domain\Internal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código PIN de un solo uso para el acceso de un empleado al portal interno.
 * Se guarda hasheado, caduca y se marca como usado tras validarse.
 *
 * @property string $code_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $used_at
 * @property int $attempts
 */
class EmployeeLoginCode extends Model
{
    protected $table = 'employee_login_codes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'code_hash',
        'expires_at',
        'used_at',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ¿El código sigue siendo válido (no usado y no caducado)? */
    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
