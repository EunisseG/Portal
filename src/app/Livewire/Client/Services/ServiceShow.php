<?php

namespace App\Livewire\Client\Services;

use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Service;
use App\Domain\Services\Services\ServiceMailboxViewer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Detalle de un servicio del cliente. Para servicios de hosting muestra,
 * en solo lectura, las cuentas de correo de su cPanel con uso/cuota.
 */
class ServiceShow extends Component
{
    public Service $service;

    public function mount(Service $service): void
    {
        $client = auth()->user()->clientAccount();
        abort_unless($client !== null, 403);
        abort_unless(auth()->user()->canClient('client.services.view'), 403);

        // Aislamiento: el servicio debe pertenecer al cliente autenticado.
        abort_unless($service->client_id === $client->id, 404);

        $this->service = $service->load(['addons', 'server', 'domains']);
    }

    /** ¿Este servicio es de hosting y puede mostrar correos? */
    public function isHosting(): bool
    {
        return $this->service->type === ServiceType::Hosting;
    }

    public function refreshMailboxes(): void
    {
        // Fuerza una consulta fresca (ignora la caché corta).
        app(ServiceMailboxViewer::class)->for($this->service, fresh: true);
        session()->flash('status', 'Correos actualizados.');
    }

    public function render(ServiceMailboxViewer $mailboxes): View
    {
        $email = $this->isHosting()
            ? $mailboxes->for($this->service)
            : ['ok' => false, 'mailboxes' => [], 'message' => null];

        return view('livewire.client.services.service-show', [
            'email' => $email,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
