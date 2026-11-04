<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrega servicios, dominios y complementos cuyo vencimiento cae dentro de una
 * ventana de días, normalizándolos como DueItem.
 */
class UpcomingRenewals
{
    /**
     * Elementos que vencen entre hoy y +$withinDays días.
     *
     * @return Collection<int, DueItem>
     */
    public function within(int $withinDays): Collection
    {
        $from = now()->startOfDay();
        $to = now()->startOfDay()->addDays($withinDays);

        return $this->collect(fn ($q) => $q->whereBetween('expires_at', [$from->toDateString(), $to->toDateString()]));
    }

    /**
     * Elementos que vencen EXACTAMENTE en $days días (para recordatorios).
     *
     * @return Collection<int, DueItem>
     */
    public function exactlyInDays(int $days): Collection
    {
        $target = now()->startOfDay()->addDays($days)->toDateString();

        return $this->collect(fn ($q) => $q->whereDate('expires_at', $target));
    }

    /**
     * @param  callable  $filter  aplica el filtro de fecha a cada query
     * @return Collection<int, DueItem>
     */
    private function collect(callable $filter): Collection
    {
        $items = collect();

        // Servicios (no cancelados).
        $services = Service::query()
            ->with('client.user')
            ->whereNotNull('expires_at')
            ->where('status', '!=', ServiceStatus::Cancelled->value)
            ->tap($filter)
            ->get();
        foreach ($services as $service) {
            $items->push($this->fromService($service));
        }

        // Dominios (no cancelados).
        $domains = Domain::query()
            ->with('client.user')
            ->whereNotNull('expires_at')
            ->where('status', '!=', DomainStatus::Cancelled->value)
            ->tap($filter)
            ->get();
        foreach ($domains as $domain) {
            $items->push($this->fromDomain($domain));
        }

        // Complementos (no cancelados) — cliente vía su servicio.
        $addons = Addon::query()
            ->with('service.client.user')
            ->whereNotNull('expires_at')
            ->where('status', '!=', AddonStatus::Cancelled->value)
            ->tap($filter)
            ->get();
        foreach ($addons as $addon) {
            $items->push($this->fromAddon($addon));
        }

        return $items->sortBy(fn (DueItem $i) => $i->dueDate)->values();
    }

    private function fromService(Service $service): DueItem
    {
        $client = $service->client;
        $user = $client?->user;

        return new DueItem(
            type: 'service',
            typeLabel: 'Servicio',
            model: $service,
            name: $service->name,
            clientId: $client?->id,
            clientName: $user?->name,
            clientEmail: $user?->email,
            clientWhatsapp: $client?->whatsapp,
            dueDate: Carbon::parse($service->expires_at),
        );
    }

    private function fromDomain(Domain $domain): DueItem
    {
        $client = $domain->client;
        $user = $client?->user;

        return new DueItem(
            type: 'domain',
            typeLabel: 'Dominio',
            model: $domain,
            name: $domain->name,
            clientId: $client?->id,
            clientName: $user?->name,
            clientEmail: $user?->email,
            clientWhatsapp: $client?->whatsapp,
            dueDate: Carbon::parse($domain->expires_at),
        );
    }

    private function fromAddon(Addon $addon): DueItem
    {
        $client = $addon->service?->client;
        $user = $client?->user;

        return new DueItem(
            type: 'addon',
            typeLabel: 'Complemento',
            model: $addon,
            name: $addon->name.' ('.$addon->service?->name.')',
            clientId: $client?->id,
            clientName: $user?->name,
            clientEmail: $user?->email,
            clientWhatsapp: $client?->whatsapp,
            dueDate: Carbon::parse($addon->expires_at),
        );
    }
}
