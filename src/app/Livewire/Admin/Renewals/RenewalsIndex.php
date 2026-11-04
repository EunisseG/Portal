<?php

namespace App\Livewire\Admin\Renewals;

use App\Domain\Notifications\Services\UpcomingRenewals;
use App\Domain\Services\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class RenewalsIndex extends Component
{
    /** Ventana en días. */
    #[Url(as: 'dias')]
    public int $window = 30;

    #[Url(as: 'tipo')]
    public string $typeFilter = '';

    public function mount(): void
    {
        // Reutiliza el permiso de servicios para el tablero de vencimientos.
        Gate::authorize('viewAny', Service::class);
    }

    public function render(UpcomingRenewals $renewals): View
    {
        $items = $renewals->within($this->window);

        if ($this->typeFilter !== '') {
            $items = $items->filter(fn ($i) => $i->type === $this->typeFilter)->values();
        }

        return view('livewire.admin.renewals.renewals-index', [
            'items' => $items,
            'windowOptions' => [7 => '7 días', 15 => '15 días', 30 => '30 días', 60 => '60 días'],
            'typeOptions' => ['service' => 'Servicios', 'domain' => 'Dominios', 'addon' => 'Complementos'],
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
