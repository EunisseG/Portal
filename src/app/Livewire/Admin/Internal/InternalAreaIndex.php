<?php

namespace App\Livewire\Admin\Internal;

use App\Domain\Internal\Models\InternalArea;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Catálogo CRUD de áreas internas (RH, Sistemas, Finanzas…). Se usan para
 * clasificar empleados y dirigir los tickets internos.
 */
class InternalAreaIndex extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public bool $is_active = true;

    /** Correos de notificación del área (varios, separados por coma). */
    public string $notify_emails = '';

    public function mount(): void
    {
        Gate::authorize('internal_areas.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('internal_areas', 'name')->ignore($this->editingId)],
            'is_active' => ['boolean'],
            'notify_emails' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('internal_areas.manage');

        $area = InternalArea::findOrFail($id);
        $this->editingId = $area->id;
        $this->name = $area->name;
        $this->is_active = $area->is_active;
        $this->notify_emails = (string) $area->notify_emails;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('internal_areas.manage');

        $data = $this->validate();

        // Valida y normaliza la lista de correos (acepta coma, punto y coma o
        // espacios como separadores). Si alguno no es un correo válido, error.
        $emails = collect(preg_split('/[,;\s]+/', (string) $this->notify_emails))
            ->map(fn ($e) => trim($e))
            ->filter(fn ($e) => $e !== '')
            ->values();

        $invalid = $emails->first(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($invalid !== null) {
            $this->addError('notify_emails', 'Hay un correo no válido: '.$invalid);

            return;
        }

        $data['notify_emails'] = $emails->isEmpty() ? null : $emails->unique()->implode(', ');

        if ($this->editingId) {
            InternalArea::whereKey($this->editingId)->update($data);
            session()->flash('status', 'Área actualizada.');
        } else {
            InternalArea::create($data);
            session()->flash('status', 'Área creada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('internal_areas.manage');

        $area = InternalArea::withCount('employees')->findOrFail($id);

        // Si tiene empleados ligados, no se borra (quedarían sin área). Mejor
        // desactivarla para que deje de ofrecerse en los formularios.
        if ($area->employees_count > 0) {
            session()->flash('error', 'El área tiene '.$area->employees_count.' empleado(s). Desactívala o reasigna a esos empleados antes de eliminarla.');

            return;
        }

        $area->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Área eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'is_active', 'notify_emails');
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $areas = InternalArea::query()->withCount('employees')->orderBy('name')->get();

        return view('livewire.admin.internal.internal-area-index', [
            'areas' => $areas,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
