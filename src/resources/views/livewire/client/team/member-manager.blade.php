<div class="mx-auto max-w-3xl">
    @include('partials.client-page-header', [
        'title' => 'Usuarios de mi cuenta',
        'subtitle' => 'Da acceso a una persona de tu equipo con permisos limitados.',
    ])

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    @error('member')
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ $message }}</div>
    @enderror

    {{-- Listado del sub-usuario --}}
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($members as $member)
                    <tr wire:key="member-{{ $member->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $member->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $member->email }}</td>
                        <td class="px-4 py-3">
                            @if ($member->isBlocked())
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Bloqueado</span>
                            @else
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" wire:click="edit({{ $member->id }})" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</button>
                                <button type="button" wire:click="toggleBlock({{ $member->id }})"
                                        class="{{ $member->isBlocked() ? 'text-green-600' : 'text-amber-600' }} hover:underline">
                                    {{ $member->isBlocked() ? 'Reactivar' : 'Bloquear' }}
                                </button>
                                <button type="button" wire:click="delete({{ $member->id }})"
                                        wire:confirm="¿Eliminar este sub-usuario? Perderá el acceso."
                                        class="text-red-600 hover:underline">Eliminar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">Aún no tienes sub-usuarios.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Botón para crear (solo si no se alcanzó el límite) --}}
    @if ($canAddMore && ! $showForm)
        <div class="mt-4">
            <button type="button" wire:click="startCreate"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + Agregar sub-usuario
            </button>
        </div>
    @endif

    {{-- Formulario alta/edición --}}
    @if ($showForm)
        <form wire:submit="save" class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-medium text-slate-900">{{ $editingId ? 'Editar sub-usuario' : 'Nuevo sub-usuario' }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nombre</label>
                    <input type="text" wire:model="name"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Correo</label>
                    @if ($editingId)
                        <input type="email" value="{{ $email }}" disabled
                               class="mt-1 block w-full rounded-md border-slate-200 bg-slate-50 text-slate-500 shadow-sm">
                        <p class="mt-1 text-xs text-slate-400">El correo no se puede cambiar desde aquí.</p>
                    @else
                        <input type="email" wire:model="email"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>
            </div>

            <div class="mt-5">
                <h3 class="text-sm font-semibold text-slate-700">Permisos</h3>
                <p class="mt-1 text-xs text-slate-400">Elige a qué puede acceder esta persona en tu cuenta.</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($permissionLabels as $value => $label)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="permissions" value="{{ $value }}"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $editingId ? 'Guardar cambios' : 'Crear sub-usuario' }}
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancelar
                </button>
            </div>
        </form>
    @endif
</div>
