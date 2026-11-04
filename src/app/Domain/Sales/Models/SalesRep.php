<?php

namespace App\Domain\Sales\Models;

use App\Domain\Services\Models\Service;
use Database\Factories\SalesRepFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ejecutivo de ventas / gestor de cuenta. Es un catálogo administrativo: NO es
 * un usuario del sistema (no inicia sesión). Cada SERVICIO puede estar ligado a
 * un ejecutivo, que es quien lo lleva (un cliente puede tener varios servicios
 * con ejecutivos distintos).
 */
class SalesRep extends Model
{
    /** @use HasFactory<SalesRepFactory> */
    use HasFactory;

    protected $table = 'sales_reps';

    protected static function newFactory(): Factory
    {
        return SalesRepFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Servicios que lleva este ejecutivo. */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'sales_rep_id');
    }

    /**
     * Clientes distintos que atiende este ejecutivo, derivados de sus servicios.
     *
     * @return \Illuminate\Support\Collection<int, \App\Domain\Clients\Models\Client>
     */
    public function clients(): \Illuminate\Support\Collection
    {
        return $this->services()
            ->with('client.user')
            ->get()
            ->pluck('client')
            ->filter()
            ->unique('id')
            ->values();
    }
}
