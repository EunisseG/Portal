<?php

namespace App\Livewire\Admin\Roles;

use App\Domain\Auth\Enums\RoleName;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    /** @var array<int, string> Permisos seleccionados (nombres). */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        if (! Gate::allows('roles.manage')) {
            abort(403);
        }
    }

    public function newRole(): void
    {
        $this->reset('editingId', 'name', 'selectedPermissions');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $roleId): void
    {
        Gate::authorize('roles.manage');

        $role = Role::with('permissions')->findOrFail($roleId);
        abort_if($this->isSystemRole($role->name), 403, 'Los roles del sistema no se pueden editar.');

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->reset('editingId', 'name', 'selectedPermissions');
        $this->resetValidation();
        $this->showForm = false;
    }

    public function save(): void
    {
        Gate::authorize('roles.manage');

        $allPermissions = Permission::pluck('name')->all();

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/',
                'unique:roles,name'.($this->editingId ? (','.$this->editingId) : ''),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'in:'.implode(',', $allPermissions)],
        ], [
            'name.regex' => 'Usa solo minúsculas, números y guion bajo (ej. soporte_nivel2).',
        ]);

        if ($this->editingId) {
            $role = Role::findOrFail($this->editingId);
            abort_if($this->isSystemRole($role->name), 403);
            $role->update(['name' => $data['name']]);
        } else {
            $role = Role::findOrCreate($data['name'], 'web');
        }

        $role->syncPermissions($data['selectedPermissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('status', $this->editingId ? 'Rol actualizado.' : 'Rol creado.');
        $this->cancel();
    }

    public function delete(int $roleId): void
    {
        Gate::authorize('roles.manage');

        $role = Role::findOrFail($roleId);
        abort_if($this->isSystemRole($role->name), 403, 'Los roles del sistema no se pueden eliminar.');

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('status', 'Rol eliminado.');
    }

    /** ¿Es un rol base del sistema (no editable/borrable)? */
    public function isSystemRole(string $name): bool
    {
        return in_array($name, array_map(fn (RoleName $r) => $r->value, RoleName::cases()), true);
    }

    /**
     * Nombre legible de un rol: etiqueta en español para los roles del sistema,
     * o el nombre tal cual para los roles personalizados que crea el admin.
     */
    public function roleLabel(string $name): string
    {
        $role = RoleName::tryFrom($name);

        return $role?->label() ?? $name;
    }

    public function render(): View
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        // Permisos agrupados por prefijo de dominio (users, clients, ...).
        $permissions = Permission::orderBy('name')->pluck('name')
            ->groupBy(fn ($name) => Str::before($name, '.'));

        return view('livewire.admin.roles.role-index', [
            'roles' => $roles,
            'permissionGroups' => $permissions,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
