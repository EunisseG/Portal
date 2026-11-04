<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Support\PermissionLabels;
use App\Domain\Users\Services\UserService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /** @var list<string> */
    public array $roles = [];

    /**
     * Permisos directos adicionales ("acceso personalizado"). Son los que se
     * otorgan al usuario además de los que ya hereda de su(s) rol(es).
     *
     * @var list<string>
     */
    public array $permissions = [];

    /**
     * Modo cliente: el usuario pertenece al módulo de Clientes. En este modo el
     * único rol posible es "client"; no se muestran otros roles ni la sección
     * de permisos personalizados (eso es solo para el equipo interno).
     */
    public bool $clientMode = false;

    public function mount(?User $user = null): void
    {
        $for = request()->query('for');

        if ($user && $user->exists) {
            Gate::authorize('update', User::class);
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->roles = $user->getRoleNames()->all();
            // Solo los permisos directos (no los heredados del rol).
            $this->permissions = $user->getDirectPermissions()->pluck('name')->all();
            // Si el usuario es un cliente, el formulario entra en modo cliente.
            $this->clientMode = $user->hasRole(RoleName::Client->value);
        } else {
            Gate::authorize('create', User::class);
            // Alta en contexto de clientes (?for=client): modo cliente.
            $this->clientMode = $for === 'client';
        }

        // En modo cliente, el rol queda fijado a "client" y sin permisos extra.
        if ($this->clientMode) {
            $this->roles = [RoleName::Client->value];
            $this->permissions = [];
        }
    }

    /**
     * Permisos que ya otorgan los roles seleccionados (herencia). Se muestran
     * marcados y bloqueados en la UI porque no se pueden "restar" con permisos
     * directos: spatie suma permisos de rol + directos.
     *
     * @return list<string>
     */
    public function rolePermissions(): array
    {
        if ($this->roles === []) {
            return [];
        }

        // El super_admin tiene acceso total (vía Gate::before), no por pivote.
        if (in_array(RoleName::SuperAdmin->value, $this->roles, true)) {
            return Permission::query()->pluck('name')->all();
        }

        return Role::query()
            ->whereIn('name', $this->roles)
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function isEditing(): bool
    {
        return $this->user !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $passwordRule = $this->isEditing()
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'password' => $passwordRule,
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in(Role::query()->pluck('name')->all())],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permission::query()->pluck('name')->all())],
        ];
    }

    public function save(UserService $users): mixed
    {
        $data = $this->validate();

        // En modo cliente el rol es siempre "client" y no hay permisos extra,
        // sin importar lo que llegue del formulario (defensa del lado servidor).
        if ($this->clientMode) {
            $data['roles'] = [RoleName::Client->value];
            $data['permissions'] = [];
        }

        // No se guardan como "directos" los permisos que ya hereda el rol:
        // spatie es aditivo y guardarlos sería redundante. Nos quedamos solo
        // con los extra ("acceso personalizado").
        $rolePermissions = $this->rolePermissions();
        $directPermissions = array_values(array_diff($data['permissions'] ?? [], $rolePermissions));

        if ($this->isEditing()) {
            $users->update($this->user, [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'] ?: null,
                'roles' => $data['roles'] ?? [],
                'permissions' => $directPermissions,
            ]);
            session()->flash('status', 'Usuario actualizado.');
        } else {
            $users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'roles' => $data['roles'] ?? [],
                'permissions' => $directPermissions,
            ]);
            session()->flash('status', 'Usuario creado.');
        }

        return $this->redirect(route('admin.users.index'), navigate: true);
    }

    public function render(): View
    {
        // Permisos agrupados por dominio, con etiquetas legibles ya resueltas
        // (grupo => [ ['name' => 'invoices.view', 'label' => 'Ver facturas'], ... ]).
        $permissionGroups = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => PermissionLabels::groupOf($name))
            ->mapWithKeys(fn ($names, $group) => [
                PermissionLabels::forGroup($group) => $names
                    ->map(fn (string $name) => [
                        'name' => $name,
                        'label' => PermissionLabels::forPermission($name),
                    ])
                    ->values()
                    ->all(),
            ]);

        return view('livewire.admin.users.user-form', [
            'availableRoles' => Role::query()->orderBy('name')->pluck('name'),
            'permissionGroups' => $permissionGroups,
            'rolePermissions' => $this->rolePermissions(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
