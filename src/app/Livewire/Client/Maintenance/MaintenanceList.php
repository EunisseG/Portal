<?php

namespace App\Livewire\Client\Maintenance;

use App\Domain\Content\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MaintenanceList extends Component
{
    use WithPagination;

    public function render(): View
    {
        $query = Maintenance::query()
            ->published()
            ->orderByDesc('starts_at');

        // Solo mantenimientos globales + los de servidores donde el cliente
        // tiene servicios.
        $client = auth()->user()?->client;
        if ($client) {
            $query->visibleToClient($client);
        }

        $items = $query->paginate(10);

        return view('livewire.client.maintenance.maintenance-list', [
            'items' => $items,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
