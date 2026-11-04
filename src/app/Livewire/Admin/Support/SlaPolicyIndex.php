<?php

namespace App\Livewire\Admin\Support;

use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Models\SlaPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Configuración de las políticas de SLA por criticidad (minutos objetivo de
 * primera respuesta y de resolución). Son 4 filas fijas (una por criticidad);
 * solo se editan sus tiempos, no se crean/borran.
 */
class SlaPolicyIndex extends Component
{
    public ?int $editingId = null;

    public int $first_response_minutes = 0;

    public int $resolution_minutes = 0;

    public function mount(): void
    {
        Gate::authorize('sla.manage');
    }

    public function edit(int $id): void
    {
        Gate::authorize('sla.manage');

        $policy = SlaPolicy::findOrFail($id);
        $this->editingId = $policy->id;
        $this->first_response_minutes = $policy->first_response_minutes;
        $this->resolution_minutes = $policy->resolution_minutes;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'first_response_minutes', 'resolution_minutes');
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize('sla.manage');

        $data = $this->validate([
            'first_response_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
        ]);

        // La resolución no puede ser menor que la primera respuesta.
        if ($data['resolution_minutes'] < $data['first_response_minutes']) {
            $this->addError('resolution_minutes', 'La resolución no puede ser menor que la primera respuesta.');

            return;
        }

        SlaPolicy::whereKey($this->editingId)->update($data);

        $this->cancel();
        session()->flash('status', 'Política de SLA actualizada.');
    }

    public function render(): View
    {
        // Orden por severidad (Crítica primero).
        $order = [
            TicketCriticality::Critical->value => 0,
            TicketCriticality::High->value => 1,
            TicketCriticality::Medium->value => 2,
            TicketCriticality::Low->value => 3,
        ];

        $all = SlaPolicy::query()->get()
            ->sortBy(fn (SlaPolicy $p) => $order[$p->criticality->value] ?? 99)
            ->values();

        // Separadas por tipo para mostrarlas en dos secciones.
        $clientPolicies = $all->where('type', \App\Domain\Tickets\Enums\TicketType::Client)->values();
        $internalPolicies = $all->where('type', \App\Domain\Tickets\Enums\TicketType::Internal)->values();

        return view('livewire.admin.support.sla-policy-index', [
            'clientPolicies' => $clientPolicies,
            'internalPolicies' => $internalPolicies,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
