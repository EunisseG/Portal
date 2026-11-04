<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Nombre</label>
            <input type="text" wire:model="name"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Correo</label>
            <input type="email" wire:model="email"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">
                    Contraseña {{ $this->isEditing() ? '(dejar en blanco para no cambiar)' : '' }}
                </label>
                <input type="password" wire:model="password" autocomplete="new-password"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Confirmar contraseña</label>
                <input type="password" wire:model="password_confirmation" autocomplete="new-password"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
        </div>

        @if ($clientMode)
            {{-- Modo cliente: rol fijo "client", sin selección de roles ni permisos. --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">Rol</label>
                <p class="mt-1 text-sm text-slate-600">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ \App\Domain\Auth\Enums\RoleName::Client->label() }}</span>
                    <span class="ml-2 text-slate-500">Las cuentas del módulo de Clientes siempre usan el rol de cliente.</span>
                </p>
            </div>
        @else
            <div>
                <label class="block text-sm font-medium text-slate-700">Roles</label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($availableRoles as $role)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" value="{{ $role }}" wire:model="roles"
                                   class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                            {{ \App\Domain\Auth\Enums\RoleName::tryFrom($role)?->label() ?? $role }}
                        </label>
                    @endforeach
                </div>
                @error('roles') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Acceso personalizado: permisos directos adicionales al rol. --}}
            <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-slate-700">Acceso personalizado (permisos)</label>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Los permisos que ya otorga el rol seleccionado aparecen marcados y bloqueados.
                Marca permisos adicionales para dar acceso puntual más allá del rol.
                Para <span class="font-medium">reducir</span> accesos, asigna un rol más acotado
                (los permisos del rol no se pueden quitar aquí).
            </p>

            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissionGroups as $group => $perms)
                    <div class="rounded-lg p-3 ring-1 ring-slate-200">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ \App\Domain\Auth\Support\PermissionLabels::forGroup($group) }}
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @foreach ($perms as $perm)
                                @php $inherited = in_array($perm['name'], $rolePermissions, true); @endphp
                                <label class="flex items-center gap-2 text-sm {{ $inherited ? 'text-slate-400' : 'text-slate-700' }}">
                                    @if ($inherited)
                                        {{-- Heredado del rol: marcado, bloqueado. --}}
                                        <input type="checkbox" checked disabled
                                               class="rounded border-slate-300 text-slate-400">
                                    @else
                                        <input type="checkbox" wire:model="permissions" value="{{ $perm['name'] }}"
                                               class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                                    @endif
                                    <span>
                                        {{ $perm['label'] }}
                                        @if ($inherited)
                                            <span class="ml-1 rounded bg-slate-100 px-1 py-0.5 text-[10px] font-medium uppercase text-slate-400">Del rol</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            @error('permissions.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
