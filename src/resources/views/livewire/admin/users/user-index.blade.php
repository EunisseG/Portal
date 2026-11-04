<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Usuarios</h1>
        @can('create', App\Models\User::class)
            <a href="{{ $tab === 'clients' ? route('admin.users.create', ['for' => 'client']) : route('admin.users.create') }}"
               class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $tab === 'clients' ? 'Nuevo cliente' : 'Nuevo usuario' }}
            </a>
        @endcan
    </div>

    {{-- Pestañas: Usuarios internos / Clientes --}}
    <div class="mt-4 border-b border-slate-200">
        <nav class="-mb-px flex gap-6 text-sm font-medium">
            <button type="button" wire:click="setTab('internal')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'internal' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Usuarios internos
            </button>
            <button type="button" wire:click="setTab('clients')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'clients' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Clientes
            </button>
        </nav>
    </div>

    <x-admin.search-input class="mt-4" placeholder="Buscar por nombre o correo..." />

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Roles</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($user->getRoleNames() as $role)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ \App\Domain\Auth\Enums\RoleName::tryFrom($role)?->label() ?? $role }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->isBlocked())
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Bloqueado</span>
                            @else
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', App\Models\User::class)
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                                @can('block', $user)
                                    <button type="button" wire:click="toggleBlock({{ $user->id }})"
                                            class="{{ $user->isBlocked() ? 'text-green-600' : 'text-red-600' }} hover:underline">
                                        {{ $user->isBlocked() ? 'Desbloquear' : 'Bloquear' }}
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                            {{ $tab === 'clients' ? 'No hay clientes que coincidan.' : 'No hay usuarios internos que coincidan.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
