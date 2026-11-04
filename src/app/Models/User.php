<?php

namespace App\Models;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Clients\Models\Client;
use App\Domain\Users\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'secondary_email',
        'password',
        'status',
        'parent_client_id',
        'employee_number',
        'internal_area_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    /** Nombre completo: nombre + apellido (si existe). */
    public function fullName(): string
    {
        return trim($this->name.' '.(string) $this->last_name);
    }

    /** Cliente (titular) al que pertenece este usuario como sub-usuario. */
    public function parentClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'parent_client_id');
    }

    /**
     * Cuenta de cliente efectiva del usuario: su propio Client si es titular,
     * o el Client del titular si es un sub-usuario. Es el método que debe usar
     * el área de cliente para resolver "lo suyo".
     */
    public function clientAccount(): ?Client
    {
        return $this->client ?: $this->parentClient;
    }

    /** ¿Este usuario es un sub-usuario de la cuenta de otro cliente? */
    public function isSubUser(): bool
    {
        return $this->parent_client_id !== null;
    }

    /**
     * ¿Puede el usuario usar una sección del portal de cliente? El titular
     * siempre puede; un sub-usuario solo si tiene el permiso acotado indicado.
     */
    public function canClient(string $permission): bool
    {
        if (! $this->isSubUser()) {
            return true;
        }

        return $this->can($permission);
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }

    /** Área interna del empleado (solo usuarios con rol "employee"). */
    public function internalArea(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Internal\Models\InternalArea::class, 'internal_area_id');
    }

    /** ¿Este usuario es un empleado interno (portal /empleados)? */
    public function isEmployee(): bool
    {
        return $this->hasRole(RoleName::Employee->value);
    }

    /**
     * Indica si el usuario pertenece al área administrativa.
     */
    public function isAdminArea(): bool
    {
        foreach (RoleName::adminRoles() as $role) {
            if ($this->hasRole($role->value)) {
                return true;
            }
        }

        return false;
    }
}
