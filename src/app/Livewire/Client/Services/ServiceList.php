<?php

namespace App\Livewire\Client\Services;

use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Support\OperationMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Página "Servicios" del cliente con dos pestañas: "Mis Servicios" y
 * "Mis Dominios". Cada pestaña se muestra según el permiso del usuario
 * (client.services.view / client.domains.view); se requiere al menos uno.
 */
class ServiceList extends Component
{
    /** Pestaña activa: "services" o "domains". */
    #[Url]
    public string $tab = 'services';

    /** Dominio en el modal de renovación (null = cerrado). */
    public ?int $renewingId = null;

    /** Años a renovar (1-5). */
    public int $renewYears = 1;

    public function mount(): void
    {
        $user = auth()->user();
        $canServices = $user->canClient('client.services.view');
        $canDomains = $user->canClient('client.domains.view');

        // Debe poder ver al menos una de las dos pestañas.
        abort_unless($canServices || $canDomains, 403);

        // Normaliza la pestaña y cae a la que el usuario sí puede ver.
        if (! in_array($this->tab, ['services', 'domains'], true)) {
            $this->tab = 'services';
        }
        if ($this->tab === 'services' && ! $canServices) {
            $this->tab = 'domains';
        }
        if ($this->tab === 'domains' && ! $canDomains) {
            $this->tab = 'services';
        }
    }

    public function setTab(string $tab): void
    {
        $user = auth()->user();

        if ($tab === 'domains' && $user->canClient('client.domains.view')) {
            $this->tab = 'domains';
        } elseif ($tab === 'services' && $user->canClient('client.services.view')) {
            $this->tab = 'services';
        }
    }

    /** Abre el modal de renovación para un dominio propio. */
    public function startRenew(int $domainId): void
    {
        abort_unless(auth()->user()->canClient('client.domains.view'), 403);
        $this->renewingId = $domainId;
        $this->renewYears = 1;
    }

    public function cancelRenew(): void
    {
        $this->renewingId = null;
    }

    /**
     * Procesa la renovación del dominio desde el cliente.
     *  - Modo manual: registra una SOLICITUD de renovación (no cambia fechas);
     *    el administrador la procesa. Queda en el log de integraciones.
     *  - Modo automático: preparado para renovar/pagar en línea (inerte hasta
     *    conectar la pasarela/proveedor).
     */
    public function renewDomain(OperationMode $mode, RecordIntegrationLog $log): void
    {
        $user = auth()->user();
        abort_unless($user->canClient('client.domains.view'), 403);

        $client = $user->clientAccount();
        abort_unless($client !== null, 403);

        $this->validate([
            'renewingId' => ['required', 'integer'],
            'renewYears' => ['required', 'integer', 'between:1,5'],
        ], [], ['renewYears' => 'años']);

        // El dominio debe pertenecer a la cuenta del cliente.
        $domain = Domain::where('client_id', $client->id)->findOrFail($this->renewingId);
        $years = (int) $this->renewYears;

        if ($mode->domainRenewalAutomatic()) {
            // Preparado para pago/renovación en línea (futuro).
            $this->renewingId = null;
            session()->flash('status', 'La renovación en línea estará disponible en breve. Te contactaremos para confirmarla.');

            return;
        }

        // Modo manual: queda como solicitud para que el equipo la procese.
        $log->record('domain.renew_request', [
            'success' => true,
            'message' => $domain->name.': solicitud de renovación por '.$years.' año(s) del cliente '.($client->user?->email ?? $client->id),
            'internal_reference' => 'domain:'.$domain->id,
        ]);

        $this->renewingId = null;
        session()->flash('status', 'Solicitud de renovación enviada ('.$years.' año(s)). Nuestro equipo la procesará y te confirmará.');
    }

    public function render(OperationMode $mode): View
    {
        $user = auth()->user();
        $client = $user->clientAccount();
        $canServices = $user->canClient('client.services.view');
        $canDomains = $user->canClient('client.domains.view');

        /** @var Collection<int, Service> $services */
        $services = ($client && $canServices)
            // El cliente solo ve los complementos ya pagados (se agregan desde
            // el admin y aparecen al pagar su orden).
            ? Service::with(['addons' => fn ($q) => $q->whereNotNull('paid_at'), 'server'])
                ->where('client_id', $client->id)
                ->orderByDesc('id')
                ->get()
            : collect();

        /** @var Collection<int, Domain> $domains */
        $domains = ($client && $canDomains)
            ? Domain::with('service')
                ->where('client_id', $client->id)
                ->orderBy('name')
                ->get()
            : collect();

        $renewingDomain = $this->renewingId
            ? $domains->firstWhere('id', $this->renewingId)
            : null;

        return view('livewire.client.services.service-list', [
            'services' => $services,
            'domains' => $domains,
            'canServices' => $canServices,
            'canDomains' => $canDomains,
            'renewingDomain' => $renewingDomain,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
