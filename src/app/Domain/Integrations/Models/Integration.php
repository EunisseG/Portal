<?php

namespace App\Domain\Integrations\Models;

use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instancia configurada de un proveedor de integración. La configuración NO
 * sensible vive en `config`; las credenciales van cifradas en `secrets`.
 */
class Integration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'capability',
        'is_enabled',
        'status',
        'last_tested_at',
        'last_error',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability' => IntegrationCapability::class,
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
            'config' => 'array',
        ];
    }

    public function secrets(): HasMany
    {
        return $this->hasMany(IntegrationSecret::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(IntegrationReference::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(IntegrationEventSubscription::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationLog::class);
    }

    /** ¿Está activa y usable? */
    public function isActive(): bool
    {
        return $this->is_enabled;
    }
}
