<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Pedidos</h1>
        <div class="flex items-center gap-2">
            {{-- Exportar con opciones (tipo de servicio y cliente) --}}
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = ! open"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Exportar
                    <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <form method="GET" action="{{ route('admin.services.export') }}" x-show="open" x-cloak x-transition
                      class="absolute right-0 z-20 mt-2 w-72 space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Exportar pedidos</p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Tipo de servicio</label>
                        <select name="type" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Todos</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Cliente</label>
                        <select name="client_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Todos</option>
                            @foreach ($clientsForExport as $client)
                                <option value="{{ $client->id }}">{{ $client->user->name }}{{ $client->company ? ' — '.$client->company : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-slate-500">Desde (registro)</label>
                            <input type="date" name="from" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500">Hasta (registro)</label>
                            <input type="date" name="to" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Formato</label>
                        <select name="format" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Descargar
                    </button>
                </form>
            </div>

            @if ($tab === 'services')
                @can('create', App\Domain\Services\Models\Service::class)
                    <a href="{{ route('admin.services.create') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Nuevo pedido
                    </a>
                @endcan
            @else
                @can('create', App\Domain\Services\Models\Domain::class)
                    <a href="{{ route('admin.domains.create') }}"
                       class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Nuevo dominio
                    </a>
                @endcan
            @endif
        </div>
    </div>

    {{-- Pestañas: cambian el contenido en la misma página --}}
    <div class="mt-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('services')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'services' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Servicios
        </button>
        <button type="button" wire:click="setTab('domains')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'domains' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Dominios
        </button>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    {{-- ===================== Pestaña: Servicios ===================== --}}
    @if ($tab === 'services')
        {{-- Tarjetas KPI por estado (clic para filtrar) --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <button type="button" wire:click="filterByStatus('pending')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-amber-300 {{ $statusFilter === 'pending' ? 'ring-2 ring-amber-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-lg font-semibold text-amber-600">{{ $kpis['pending'] }}</span>
                <span class="text-sm font-medium text-slate-600">Configuración pendiente</span>
            </button>
            <button type="button" wire:click="filterByStatus('active')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-green-300 {{ $statusFilter === 'active' ? 'ring-2 ring-green-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $kpis['active'] }}</span>
                <span class="text-sm font-medium text-slate-600">Activo</span>
            </button>
            <button type="button" wire:click="filterByStatus('suspended')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-300 {{ $statusFilter === 'suspended' ? 'ring-2 ring-slate-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['suspended'] }}</span>
                <span class="text-sm font-medium text-slate-600">Suspendido</span>
            </button>
            <button type="button" wire:click="filterByStatus('')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-blue-300 {{ $statusFilter === '' ? 'ring-2 ring-blue-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['total'] }}</span>
                <span class="text-sm font-medium text-slate-600">Total</span>
            </button>
        </div>

        {{-- Gráfica de distribución de pedidos por estado (barras) --}}
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Distribución de pedidos por estado</h2>
            @if ($kpis['total'] > 0)
                <div class="mt-4 h-64">
                    <canvas data-status-chart
                            data-labels='@json($chart['labels'])'
                            data-values='@json($chart['data'])'></canvas>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">Aún no hay pedidos para graficar.</p>
            @endif
        </div>

        @can('services.manage')
            <form method="POST" action="{{ route('admin.services.import') }}" enctype="multipart/form-data"
                  class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                @csrf
                <div class="text-sm text-slate-600">
                    <span class="font-medium text-slate-800">Importar servicios:</span>
                    columnas <code>cliente_correo, servicio, tipo, ciclo, total, moneda, inicia, vence</code>.
                </div>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                       class="text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">
                <button type="submit"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Importar
                </button>
                @error('file')
                    <span class="text-sm text-red-600">{{ $message }}</span>
                @enderror
            </form>
        @endcan

        <div class="mt-4 flex flex-wrap gap-3">
            <x-admin.search-input placeholder="Buscar por servicio o cliente..." :hint="false" />
            <select wire:model.live="typeFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los tipos</option>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los estados</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Servicio</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Vence</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($services as $service)
                        <tr wire:key="service-{{ $service->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $service->name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $service->client->user->name }}
                                <div class="text-xs text-slate-400">{{ $service->client->company }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $service->type?->label() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">
                                    {{ $service->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $service->expires_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.services.show', $service) }}" class="text-slate-600 hover:underline">Ver</a>
                                    @can('update', App\Domain\Services\Models\Service::class)
                                        @if ($service->isLockedByPayment())
                                            <span class="text-green-700" title="El pedido ya está pagado y no se puede editar.">Pagado</span>
                                        @else
                                            <a href="{{ route('admin.services.edit', $service) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay pedidos que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $services->links() }}</div>
    @endif

    {{-- ===================== Pestaña: Dominios ===================== --}}
    @if ($tab === 'domains')
        {{-- Tarjetas KPI por estado del dominio (clic para filtrar) --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <button type="button" wire:click="filterByStatus('active')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-green-300 {{ $statusFilter === 'active' ? 'ring-2 ring-green-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $domainKpis['active'] }}</span>
                <span class="text-sm font-medium text-slate-600">Activo</span>
            </button>
            <button type="button" wire:click="filterByStatus('pending')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-amber-300 {{ $statusFilter === 'pending' ? 'ring-2 ring-amber-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-lg font-semibold text-amber-600">{{ $domainKpis['pending'] }}</span>
                <span class="text-sm font-medium text-slate-600">Pendiente</span>
            </button>
            <button type="button" wire:click="filterByStatus('expired')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-red-300 {{ $statusFilter === 'expired' ? 'ring-2 ring-red-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-lg font-semibold text-red-600">{{ $domainKpis['expired'] }}</span>
                <span class="text-sm font-medium text-slate-600">Vencido</span>
            </button>
            <button type="button" wire:click="filterByStatus('cancelled')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-300 {{ $statusFilter === 'cancelled' ? 'ring-2 ring-slate-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $domainKpis['cancelled'] }}</span>
                <span class="text-sm font-medium text-slate-600">Cancelado</span>
            </button>
            <button type="button" wire:click="filterByStatus('')"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 text-left shadow-sm ring-1 ring-slate-900/5 transition hover:ring-blue-300 {{ $statusFilter === '' ? 'ring-2 ring-blue-400' : '' }}">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $domainKpis['total'] }}</span>
                <span class="text-sm font-medium text-slate-600">Total</span>
            </button>
        </div>

        {{-- Gráfica de distribución de dominios por estado (barras) --}}
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Distribución de dominios por estado</h2>
            @if ($domainKpis['total'] > 0)
                <div class="mt-4 h-64">
                    <canvas data-status-chart
                            data-labels='@json($domainChart['labels'])'
                            data-values='@json($domainChart['data'])'></canvas>
                </div>
            @else
                <p class="mt-3 text-sm text-slate-500">Aún no hay dominios para graficar.</p>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-admin.search-input placeholder="Buscar por dominio o cliente..." :hint="false" />
            <select wire:model.live="statusFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los estados</option>
                @foreach ($domainStatusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Dominio</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Servicio</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Vence</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($domains as $domain)
                        <tr wire:key="domain-{{ $domain->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $domain->name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $domain->client?->user?->name ?: '—' }}
                                <div class="text-xs text-slate-400">{{ $domain->client?->company }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $domain->service?->name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $domain->status?->badgeClasses() }}">
                                    {{ $domain->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $domain->expires_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    @can('update', App\Domain\Services\Models\Domain::class)
                                        <a href="{{ route('admin.domains.edit', $domain) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                    @endcan
                                    @can('delete', App\Domain\Services\Models\Domain::class)
                                        <button type="button" wire:click="deleteDomain({{ $domain->id }})"
                                                wire:confirm="¿Eliminar este dominio?"
                                                class="text-red-600 hover:underline">Eliminar</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay dominios que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $domains->links() }}</div>
    @endif

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
