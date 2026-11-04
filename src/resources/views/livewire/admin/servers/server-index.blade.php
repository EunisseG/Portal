<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Servidores y planes de hosting</h1>
        @if ($tab === 'servers')
            @can('create', App\Domain\Services\Models\Server::class)
                <a href="{{ route('admin.servers.create') }}"
                   class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nuevo servidor
                </a>
            @endcan
        @else
            @can('create', App\Domain\Services\Models\Plan::class)
                <a href="{{ route('admin.plans.create') }}"
                   class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nuevo plan
                </a>
            @endcan
        @endif
    </div>

    {{-- Pestañas: cambian el contenido en la misma página --}}
    <div class="mt-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('servers')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'servers' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Servidores de hosting
        </button>
        <button type="button" wire:click="setTab('plans')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'plans' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Planes de hosting
        </button>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <x-admin.search-input class="mt-4"
        :placeholder="$tab === 'servers' ? 'Buscar por nombre, hostname o IP...' : 'Buscar por nombre de plan...'" />

    {{-- ===================== Pestaña: Servidores ===================== --}}
    @if ($tab === 'servers')
        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Gestor</th>
                        <th class="px-4 py-3">Conexión</th>
                        <th class="px-4 py-3">Servicios</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($servers as $server)
                        <tr wire:key="server-{{ $server->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500">{{ $server->hostname ?: '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $server->ip_address ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($server->panel)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $server->panel->label() }}</span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($server->hasPanel())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Configurada
                                    </span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Sin conexión</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $server->services_count }}</td>
                            <td class="px-4 py-3">
                                @if ($server->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', App\Domain\Services\Models\Server::class)
                                        @if ($server->hasPanel())
                                            <button type="button" wire:click="testConnection({{ $server->id }})"
                                                    wire:loading.attr="disabled" wire:target="testConnection({{ $server->id }})"
                                                    title="Probar conexión"
                                                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50">
                                                <span wire:loading wire:target="testConnection({{ $server->id }})" class="text-xs">…</span>
                                                <svg wire:loading.remove wire:target="testConnection({{ $server->id }})" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z"/></svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.servers.edit', $server) }}"
                                           title="Configurar / editar"
                                           class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 0 1-2.29.95c-1.37-.84-2.94.73-2.1 2.1.54.9.1 2.07-.95 2.29-1.56.38-1.56 2.6 0 2.98a1.53 1.53 0 0 1 .95 2.29c-.84 1.37.73 2.94 2.1 2.1a1.53 1.53 0 0 1 2.29.95c.38 1.56 2.6 1.56 2.98 0a1.53 1.53 0 0 1 2.29-.95c1.37.84 2.94-.73 2.1-2.1a1.53 1.53 0 0 1 .95-2.29c1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 0 1-.95-2.29c.84-1.37-.73-2.94-2.1-2.1a1.53 1.53 0 0 1-2.29-.95Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        </a>
                                    @endcan
                                    @can('delete', App\Domain\Services\Models\Server::class)
                                        <button type="button" wire:click="delete({{ $server->id }})"
                                                wire:confirm="¿Eliminar este servidor?"
                                                title="Eliminar"
                                                class="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.68.1 1.02.16m-1.02-.16L18.16 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.11 48.11 0 0 0-3.48-.4m-11 .55c.34-.06.68-.11 1.02-.16m0 0a48.11 48.11 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0"/></svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                        @if (isset($testResults[$server->id]))
                            <tr wire:key="srv-test-{{ $server->id }}">
                                <td colspan="7" class="px-4 pb-3 pt-0">
                                    <div class="rounded-md px-3 py-2 text-xs ring-1 {{ $testResults[$server->id]['ok'] ? 'bg-green-50 text-green-700 ring-green-600/10' : 'bg-red-50 text-red-700 ring-red-600/10' }}">
                                        <span class="font-medium">{{ $testResults[$server->id]['ok'] ? '✓' : '✗' }} {{ $server->name }}:</span>
                                        {{ $testResults[$server->id]['msg'] }}
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay servidores registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $servers->links() }}</div>
    @endif

    {{-- ===================== Pestaña: Planes ===================== --}}
    @if ($tab === 'plans')
        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Precios</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($plans as $plan)
                        <tr wire:key="plan-{{ $plan->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $plan->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan->type?->label() }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $plan->prices->count() }} ciclo(s)</td>
                            <td class="px-4 py-3">
                                @if ($plan->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactivo</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', App\Domain\Services\Models\Plan::class)
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay planes registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $plans->links() }}</div>
    @endif
</div>
