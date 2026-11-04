<?php

namespace App\Livewire\Admin\Internal;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Internal\Models\InternalArea;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alta y gestión de empleados internos. Cada empleado es un usuario con rol
 * "employee" que accede al portal interno (/empleados) con su número de
 * empleado + PIN al correo. No usa contraseña para iniciar sesión (se guarda
 * una aleatoria solo para satisfacer el esquema).
 */
class EmployeeIndex extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';
    public string $last_name = '';
    public string $email = '';
    public string $employee_number = '';
    public string $internal_area_id = '';

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('employees.manage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($this->editingId)],
            'internal_area_id' => ['required', Rule::exists('internal_areas', 'id')],
        ];
    }

    public function newEmployee(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('employees.manage');

        $user = $this->employeeQuery()->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = (string) $user->name;
        $this->last_name = (string) $user->last_name;
        $this->email = (string) $user->email;
        $this->employee_number = (string) $user->employee_number;
        $this->internal_area_id = (string) ($user->internal_area_id ?? '');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('employees.manage');

        $data = $this->validate();

        if ($this->editingId) {
            $user = $this->employeeQuery()->findOrFail($this->editingId);
            $user->forceFill([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?: null,
                'email' => $data['email'],
                'employee_number' => $data['employee_number'],
                'internal_area_id' => $data['internal_area_id'],
            ])->save();

            session()->flash('status', 'Empleado actualizado.');
        } else {
            $user = User::create([
                'name' => $data['name'],
                'last_name' => $data['last_name'] ?: null,
                'email' => $data['email'],
                'employee_number' => $data['employee_number'],
                'internal_area_id' => $data['internal_area_id'],
                // Contraseña aleatoria: el empleado entra por PIN, no la usa.
                'password' => Hash::make(Str::random(40)),
                'status' => UserStatus::Active->value,
                // Se marca el correo como verificado: su identidad se valida con
                // el PIN que recibe en ese mismo correo.
                'email_verified_at' => now(),
            ]);
            $user->assignRole(RoleName::Employee->value);

            session()->flash('status', 'Empleado dado de alta. Podrá acceder con su número de empleado y el código que reciba por correo.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('employees.manage');

        $user = $this->employeeQuery()->findOrFail($id);
        $user->delete();

        session()->flash('status', 'Empleado eliminado.');
    }

    /** Query base: solo usuarios con rol employee. */
    private function employeeQuery()
    {
        return User::query()->whereHas('roles', fn ($q) => $q->where('name', RoleName::Employee->value));
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'last_name', 'email', 'employee_number', 'internal_area_id', 'showForm');
        $this->resetValidation();
    }

    public function render(): View
    {
        $employees = $this->employeeQuery()
            ->with('internalArea')
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('employee_number', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.internal.employee-index', [
            'employees' => $employees,
            'areas' => InternalArea::orderBy('name')->get(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
