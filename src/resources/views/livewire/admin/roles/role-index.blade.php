<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Roles y permisos</h1>
            <p class="mt-1 text-slate-600">Crea roles personalizados combinando los permisos existentes.</p>
        </div>
        @unless ($showForm)
            <button wire:click="newRole"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Nuevo rol
            </button>
        @endunless
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    {{-- Formulario de creación/edición --}}
    @if ($showForm)
        <form wire:submit="save" class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar rol' : 'Nuevo rol' }}</h2>

            <div class="mt-4 max-w-md">
                <label class="block text-sm font-medium text-slate-700">Nombre del rol</label>
                <input type="text" wire:model="name" placeholder="ej. soporte_nivel2"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5">
                <div class="text-sm font-medium text-slate-700">Permisos</div>
                <div class="mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissionGroups as $group => $perms)
                        <div class="rounded-lg ring-1 ring-slate-200 p-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ \App\Domain\Auth\Support\PermissionLabels::forGroup($group) }}</div>
                            <div class="mt-2 space-y-1.5">
                                @foreach ($perms as $perm)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $perm }}"
                                               class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                                        {{ \App\Domain\Auth\Support\PermissionLabels::forPermission($perm) }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('selectedPermissions.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-5 flex items-center gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    {{ $editingId ? 'Guardar cambios' : 'Crear rol' }}
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancelar
                </button>
            </div>
        </form>
    @endif

    {{-- Listado de roles --}}
    <div class="mt-6 space-y-4">
        @foreach ($roles as $role)
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-slate-900">{{ $this->roleLabel($role->name) }}</h2>
                        @if ($this->isSystemRole($role->name))
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-400">Sistema</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-slate-500">{{ $role->permissions->count() }} permisos</span>
                        @unless ($this->isSystemRole($role->name))
                            <button wire:click="edit({{ $role->id }})" class="text-sm text-slate-600 hover:text-slate-900 hover:underline">Editar</button>
                            <button wire:click="delete({{ $role->id }})"
                                    wire:confirm="¿Eliminar el rol {{ $this->roleLabel($role->name) }}?"
                                    class="text-sm text-red-600 hover:underline">Borrar</button>
                        @endunless
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @forelse ($role->permissions as $permission)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ \App\Domain\Auth\Support\PermissionLabels::forPermission($permission->name) }}</span>
                    @empty
                        <span class="text-sm text-slate-400">
                            @if ($role->name === 'super_admin')
                                Acceso total (todos los permisos).
                            @else
                                Sin permisos administrativos.
                            @endif
                        </span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
