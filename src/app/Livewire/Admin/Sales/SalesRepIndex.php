<?php

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Models\SalesRep;
use App\Domain\Services\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Catálogo CRUD de ejecutivos de ventas (gestores de cuenta). Pantalla única:
 * formulario a la izquierda, listado a la derecha. Se liga a los clientes desde
 * su propia ficha.
 */
class SalesRepIndex extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $is_active = true;

    // ===== Reasignación de cartera (dar de baja un ejecutivo) =====

    /** Ejecutivo cuya cartera (servicios) se está reasignando (null = cerrado). */
    public ?int $reassigningId = null;

    /** IDs de servicios marcados para reasignar. */
    public array $selectedServices = [];

    /** Ejecutivo destino elegido para los servicios marcados. */
    public ?int $reassignTo = null;

    public function mount(): void
    {
        Gate::authorize('sales_reps.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('sales_reps.manage');

        $rep = SalesRep::findOrFail($id);
        $this->editingId = $rep->id;
        $this->name = $rep->name;
        $this->email = (string) $rep->email;
        $this->phone = (string) $rep->phone;
        $this->is_active = $rep->is_active;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('sales_reps.manage');

        $data = $this->validate();
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'is_active' => $data['is_active'],
        ];

        if ($this->editingId) {
            SalesRep::whereKey($this->editingId)->update($payload);
            session()->flash('status', 'Ejecutivo actualizado.');
        } else {
            SalesRep::create($payload);
            session()->flash('status', 'Ejecutivo creado.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('sales_reps.manage');

        $rep = SalesRep::withCount('services')->findOrFail($id);

        // Si lleva servicios, no se borra: primero hay que reasignarlos para no
        // dejarlos sin ejecutivo por accidente. Abre el panel de reasignación.
        if ($rep->services_count > 0) {
            $this->startReassign($id);
            session()->flash('error', 'El ejecutivo lleva '.$rep->services_count.' servicio(s). Reasigna su cartera antes de eliminarlo.');

            return;
        }

        $rep->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Ejecutivo eliminado.');
    }

    /** Abre el panel para reasignar los servicios de un ejecutivo. */
    public function startReassign(int $repId): void
    {
        Gate::authorize('sales_reps.manage');

        $this->reassigningId = $repId;
        $this->selectedServices = [];
        $this->reassignTo = null;
        $this->resetValidation();
    }

    public function cancelReassign(): void
    {
        $this->reset('reassigningId', 'selectedServices', 'reassignTo');
        $this->resetValidation();
    }

    /** Marca/desmarca todos los servicios del ejecutivo en reasignación. */
    public function toggleAllServices(): void
    {
        if ($this->reassigningId === null) {
            return;
        }

        $all = Service::where('sales_rep_id', $this->reassigningId)->pluck('id')->all();
        $this->selectedServices = count($this->selectedServices) === count($all) ? [] : $all;
    }

    /**
     * Reasigna los servicios marcados al ejecutivo destino. Permite repartir en
     * varias pasadas (marcar unos → destino A → aplicar; luego otros → destino B).
     * No toca el histórico de eventos (sales_rep_id congelado en sales_events).
     */
    public function reassignSelected(): void
    {
        Gate::authorize('sales_reps.manage');

        $this->validate([
            'reassignTo' => ['required', 'integer', 'exists:sales_reps,id', 'different:reassigningId'],
            'selectedServices' => ['required', 'array', 'min:1'],
            'selectedServices.*' => ['integer'],
        ], [
            'reassignTo.required' => 'Elige el ejecutivo destino.',
            'reassignTo.different' => 'El destino debe ser un ejecutivo distinto.',
            'selectedServices.required' => 'Selecciona al menos un servicio.',
            'selectedServices.min' => 'Selecciona al menos un servicio.',
        ]);

        // Solo mueve servicios que realmente son del ejecutivo en baja (evita
        // mover servicios ajenos si llegara un id manipulado).
        $moved = Service::where('sales_rep_id', $this->reassigningId)
            ->whereIn('id', $this->selectedServices)
            ->update(['sales_rep_id' => $this->reassignTo]);

        $this->selectedServices = [];
        $this->reassignTo = null;

        // Si ya no quedan servicios, el panel permite eliminar al ejecutivo.
        session()->flash('status', 'Reasignados '.$moved.' servicio(s).');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'phone', 'is_active');
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $reps = SalesRep::query()
            ->withCount('services')
            ->orderBy('name')
            ->get();

        // Datos del panel de reasignación (si está abierto).
        $reassigningRep = null;
        $reassignServices = collect();
        $reassignTargets = collect();
        if ($this->reassigningId !== null) {
            $reassigningRep = $reps->firstWhere('id', $this->reassigningId)
                ?? SalesRep::withCount('services')->find($this->reassigningId);
            $reassignServices = Service::with('client.user')
                ->where('sales_rep_id', $this->reassigningId)
                ->orderBy('name')
                ->get();
            $reassignTargets = SalesRep::where('is_active', true)
                ->where('id', '!=', $this->reassigningId)
                ->orderBy('name')
                ->get();
        }

        return view('livewire.admin.sales.sales-rep-index', [
            'reps' => $reps,
            'reassigningRep' => $reassigningRep,
            'reassignServices' => $reassignServices,
            'reassignTargets' => $reassignTargets,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
