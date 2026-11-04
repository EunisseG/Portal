<?php

namespace App\Livewire\Admin\Clients;

use App\Domain\Clients\Models\Client;
use App\Domain\Clients\Services\ClientDashboardSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Vista de solo lectura del panel de un cliente, para uso administrativo.
 * No cambia de sesión: el admin ve el resumen del cliente dentro del área admin.
 */
class ClientPanel extends Component
{
    public Client $client;

    public function mount(Client $client): void
    {
        Gate::authorize('view', Client::class);
        $this->client = $client->load('user');
    }

    public function render(ClientDashboardSummary $summaries): View
    {
        return view('livewire.admin.clients.client-panel', [
            'summary' => $summaries->for($this->client),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
