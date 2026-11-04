<?php

namespace App\Domain\Content\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Content\Enums\MaintenanceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso de mantenimiento programado. El admin lo gestiona; el cliente ve los
 * que estén publicados.
 *
 * Alcance del mantenimiento:
 *  - Global: service_type = null → afecta a todos los clientes.
 *  - Por tipo de servicio: service_type = email|license|other → afecta a los
 *    clientes con al menos un servicio de ese tipo.
 *  - Hosting: service_type = hosting. Si server_id es null, afecta a todos los
 *    clientes con hosting; si server_id está definido, solo a los que tienen
 *    hosting en ese servidor.
 */
class Maintenance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'service_type',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'status',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'service_type' => ServiceType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @param  Builder<Maintenance>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * ¿Es un mantenimiento global (afecta a todos los clientes)?
     */
    public function isGlobal(): bool
    {
        return $this->service_type === null;
    }

    /**
     * Mantenimientos visibles para un cliente concreto: los globales y los
     * cuyo alcance (tipo de servicio, y servidor si es hosting) coincide con
     * algún servicio del cliente.
     *
     * @param  Builder<Maintenance>  $query
     */
    public function scopeVisibleToClient(Builder $query, Client $client): void
    {
        // Tipos de servicio que tiene el cliente (como string; 'type' está
        // casteado a enum, por eso normalizamos a su value).
        $types = $client->services()
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->map(fn ($t) => $t instanceof ServiceType ? $t->value : $t)
            ->all();

        // Servidores donde el cliente tiene hosting.
        $hostingServerIds = $client->services()
            ->where('type', ServiceType::Hosting->value)
            ->whereNotNull('server_id')
            ->distinct()
            ->pluck('server_id')
            ->all();

        $query->where(function (Builder $q) use ($types, $hostingServerIds) {
            // Globales.
            $q->whereNull('service_type');

            // Por tipo no-hosting que el cliente posee.
            $nonHosting = array_values(array_filter($types, fn ($t) => $t !== ServiceType::Hosting->value));
            if (! empty($nonHosting)) {
                $q->orWhereIn('service_type', $nonHosting);
            }

            // Hosting: coincide si el cliente tiene hosting.
            if (in_array(ServiceType::Hosting->value, $types, true)) {
                $q->orWhere(function (Builder $h) use ($hostingServerIds) {
                    $h->where('service_type', ServiceType::Hosting->value)
                        ->where(function (Builder $s) use ($hostingServerIds) {
                            // Hosting global (sin servidor) o de un servidor del cliente.
                            $s->whereNull('server_id');
                            if (! empty($hostingServerIds)) {
                                $s->orWhereIn('server_id', $hostingServerIds);
                            }
                        });
                });
            }
        });
    }

    /**
     * Aplica el alcance del mantenimiento a una consulta de servicios.
     *
     * @param  Builder<\App\Domain\Services\Models\Service>  $services
     */
    private function constrainServicesToScope(Builder $services): void
    {
        if ($this->isGlobal()) {
            return;
        }

        $services->where('type', $this->service_type->value);

        // Solo el hosting puede acotarse a un servidor concreto.
        if ($this->service_type === ServiceType::Hosting && $this->server_id !== null) {
            $services->where('server_id', $this->server_id);
        }
    }

    /**
     * Usuarios (con rol cliente) destinatarios de este mantenimiento.
     *
     * @return Collection<int, User>
     */
    public function recipientUsers(): Collection
    {
        $query = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', \App\Domain\Auth\Enums\RoleName::Client->value));

        if (! $this->isGlobal()) {
            $query->whereHas('client.services', fn ($q) => $this->constrainServicesToScope($q));
        }

        return $query->get();
    }

    /**
     * Clientes destinatarios (con usuario cargado) con número de WhatsApp.
     *
     * @return Collection<int, Client>
     */
    public function recipientClientsWithWhatsapp(): Collection
    {
        $query = Client::query()
            ->whereNotNull('whatsapp')
            ->where('whatsapp', '!=', '')
            ->with('user');

        if (! $this->isGlobal()) {
            $query->whereHas('services', fn ($q) => $this->constrainServicesToScope($q));
        }

        return $query->get();
    }
}
