<div>
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
        </a>
        <span class="text-slate-300">/</span>
        <span class="font-medium text-slate-700">Clientes</span>
    </nav>

    {{-- Tarjetas KPI --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-500 text-lg font-bold text-white">{{ $kpiActive }}</span>
            <span class="text-sm font-medium text-slate-600">Activo</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-500 text-lg font-bold text-white">{{ $kpiSuspended }}</span>
            <span class="text-sm font-medium text-slate-600">Suspendido</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-500 text-lg font-bold text-white">{{ $kpiCancelled }}</span>
            <span class="text-sm font-medium text-slate-600">Cancelado</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500 text-lg font-bold text-white">{{ $kpiTotal }}</span>
            <span class="text-sm font-medium text-slate-600">Total</span>
        </div>
    </div>

    {{-- Gráfica de distribución de clientes por estado (barras) --}}
    <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Distribución de clientes por estado</h2>
        @if ($kpiTotal > 0)
            <div class="mt-4 h-64">
                <canvas data-status-chart
                        data-labels='@json($chart['labels'])'
                        data-values='@json($chart['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">Aún no hay clientes para graficar.</p>
        @endif
    </div>

    {{-- Encabezado con descripción --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h1 class="text-lg font-semibold text-slate-900">Clientes</h1>
        <p class="mt-1 text-sm text-slate-500">Administra las cuentas de cliente, revisa sus registros y gestiónalos desde aquí.</p>

        @if (session('error'))
            <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
        @endif

        {{-- Barra: buscador + nuevo cliente --}}
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <x-admin.search-input class="flex-1 sm:max-w-sm" width="w-full"
                placeholder="Introduzca el texto a buscar..." :hint="false" />
            {{-- Filtro por ejecutivo de ventas --}}
            <select wire:model.live="salesRepFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Ejecutivo: todos</option>
                <option value="0">Sin asignar</option>
                @foreach ($salesReps as $rep)
                    <option value="{{ $rep->id }}">{{ $rep->name }}</option>
                @endforeach
            </select>
            @can('create', App\Domain\Clients\Models\Client::class)
                <a href="{{ route('admin.clients.create') }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nuevo cliente
                </a>
            @endcan
        </div>

        {{-- Tabla --}}
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3">Nombre</th>
                        <th class="px-3 py-3">Empresa</th>
                        <th class="px-3 py-3">Ejecutivo</th>
                        <th class="px-3 py-3">Estado</th>
                        <th class="px-3 py-3">Fecha de registro</th>
                        <th class="px-3 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($clients as $client)
                        @php
                            $user = $client->user;
                            $parts = preg_split('/\s+/', trim((string) ($user?->name ?: '?')));
                            $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
                        @endphp
                        <tr wire:key="client-{{ $client->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">{{ $initials ?: '?' }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.clients.show', $client) }}" class="block truncate font-medium text-indigo-600 hover:underline">{{ $user?->name ?: '—' }}</a>
                                        <div class="truncate text-slate-500">{{ $user?->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $client->company ?: '—' }}</td>
                            <td class="px-3 py-3 text-slate-600">
                                @php $repNames = $client->services->pluck('salesRep')->filter()->pluck('name')->unique()->values(); @endphp
                                {{ $repNames->isNotEmpty() ? $repNames->implode(', ') : '—' }}
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $client->status?->badgeClasses() }}">
                                    {{ $client->status?->label() }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $client->created_at?->translatedFormat('d M Y H:i') ?: '—' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Menú de estado: suspender / cancelar / reactivar. --}}
                                    @can('update', App\Domain\Clients\Models\Client::class)
                                        <div class="relative" x-data="{ open: false }">
                                            <button type="button" @click="open = ! open" @click.outside="open = false"
                                                    title="Cambiar estado"
                                                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"/></svg>
                                            </button>
                                            <div x-show="open" x-cloak x-transition
                                                 class="absolute right-0 z-20 mt-1 w-44 overflow-hidden rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-slate-900/5">
                                                @if ($client->status !== \App\Domain\Clients\Enums\ClientStatus::Active)
                                                    <button type="button" wire:click="setStatus({{ $client->id }}, 'active')" @click="open = false"
                                                            class="block w-full px-4 py-2 text-left text-green-700 hover:bg-slate-50">Reactivar</button>
                                                @endif
                                                @if ($client->status !== \App\Domain\Clients\Enums\ClientStatus::Suspended)
                                                    <button type="button" wire:click="setStatus({{ $client->id }}, 'suspended')" @click="open = false"
                                                            wire:confirm="¿Suspender a este cliente? Se bloqueará su acceso al portal."
                                                            class="block w-full px-4 py-2 text-left text-amber-700 hover:bg-slate-50">Suspender</button>
                                                @endif
                                                @if ($client->status !== \App\Domain\Clients\Enums\ClientStatus::Cancelled)
                                                    <button type="button" wire:click="setStatus({{ $client->id }}, 'cancelled')" @click="open = false"
                                                            wire:confirm="¿Cancelar a este cliente? Se bloqueará su acceso al portal."
                                                            class="block w-full px-4 py-2 text-left text-red-700 hover:bg-slate-50">Cancelar</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endcan
                                    {{-- Editar: abre el detalle, donde los datos se editan en línea. --}}
                                    <a href="{{ route('admin.clients.show', $client) }}" title="Ver / editar"
                                       class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                                    </a>
                                    @can('delete', App\Domain\Clients\Models\Client::class)
                                        @if (($client->stamped_invoices_count ?? 0) > 0)
                                            <span class="cursor-help rounded-md p-2 text-slate-300"
                                                  title="No se puede eliminar: tiene facturas timbradas (CFDI). Debe conservarse el histórico fiscal.">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                            </span>
                                        @else
                                            <button type="button" wire:click="delete({{ $client->id }})"
                                                    wire:confirm="¿Eliminar a este cliente? Se borrarán de forma permanente su usuario, sus sub-usuarios y todos sus servicios, dominios, facturas y tickets. Esta acción no se puede deshacer."
                                                    title="Eliminar"
                                                    class="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-8 text-center text-slate-500">No hay clientes que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <div>{{ $clients->links() }}</div>
            {{-- Exportar con opciones: rango de fechas (por registro) y formato. --}}
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = ! open"
                        class="inline-flex items-center gap-1.5 rounded-md bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Exportar clientes
                    <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <form method="GET" action="{{ route('admin.clients.export') }}" x-show="open" x-cloak x-transition
                      class="absolute right-0 bottom-full z-20 mb-2 w-72 space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Exportar clientes</p>
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
                        <label class="block text-xs text-slate-500">Ejecutivo</label>
                        <select name="rep" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="" @selected($salesRepFilter === '')>Todos</option>
                            <option value="0" @selected($salesRepFilter === '0')>Sin asignar</option>
                            @foreach ($salesReps as $rep)
                                <option value="{{ $rep->id }}" @selected((string) $salesRepFilter === (string) $rep->id)>{{ $rep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500">Formato</label>
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
        </div>
    </div>

    {{-- Importación de clientes (se conserva, debajo). --}}
    @can('clients.manage')
        <form method="POST" action="{{ route('admin.clients.import') }}" enctype="multipart/form-data"
              class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            @csrf
            <div class="text-sm text-slate-600">
                <span class="font-medium text-slate-800">Importar clientes:</span>
                columnas <code>nombre, correo, empresa, telefono, whatsapp, rfc, direccion</code>.
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

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
