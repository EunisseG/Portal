<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Tickets internos</h1>
            <p class="mt-1 text-sm text-slate-500">Solicitudes levantadas por el personal de la empresa.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Pestañas: activos / cerrados --}}
    <div class="mt-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('active')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'active' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Activos
        </button>
        <button type="button" wire:click="setTab('closed')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'closed' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Cerrados
        </button>
    </div>

    {{-- Tarjetas KPI por estado --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['open'] }}</span>
            <span class="text-sm font-medium text-slate-600">Abierto</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-lg font-semibold text-amber-600">{{ $kpis['pending'] }}</span>
            <span class="text-sm font-medium text-slate-600">En espera</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $kpis['answered'] }}</span>
            <span class="text-sm font-medium text-slate-600">Respondido</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-lg font-semibold text-emerald-600">{{ $kpis['resolved'] }}</span>
            <span class="text-sm font-medium text-slate-600">Resuelto</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['closed'] }}</span>
            <span class="text-sm font-medium text-slate-600">Cerrado</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-lg font-semibold text-indigo-600">{{ $kpis['total'] }}</span>
            <span class="text-sm font-medium text-slate-600">Total</span>
        </div>
    </div>

    {{-- Gráfica de distribución de tickets internos por estado (barras) --}}
    <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Distribución de tickets internos por estado</h2>
        @if ($kpis['total'] > 0)
            <div class="mt-4 h-64">
                <canvas data-status-chart
                        data-labels='@json($chart['labels'])'
                        data-values='@json($chart['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">Aún no hay tickets internos para graficar.</p>
        @endif
    </div>

    {{-- Filtros --}}
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <x-admin.search-input placeholder="Buscar por número, asunto o empleado..." :hint="false" />
        <select wire:model.live="areaFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Área: todas</option>
            @foreach ($areas as $area)
                <option value="{{ $area->id }}">{{ $area->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="priorityFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Prioridad: todas</option>
            @foreach ($priorityOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Número</th>
                    <th class="px-4 py-3">Asunto</th>
                    <th class="px-4 py-3">Empleado</th>
                    <th class="px-4 py-3">Área</th>
                    <th class="px-4 py-3">Prioridad</th>
                    <th class="px-4 py-3">Criticidad</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">SLA</th>
                    <th class="px-4 py-3">Última respuesta</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($tickets as $ticket)
                    <tr wire:key="internal-ticket-{{ $ticket->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $ticket->number }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $ticket->openedBy?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $ticket->internalArea?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->priority?->badgeClasses() }}">
                                {{ $ticket->priority?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->criticality?->badgeClasses() }}">
                                {{ $ticket->criticality?->label() ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->status?->badgeClasses() }}">
                                {{ $ticket->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @php $sla = $slaBadges[$ticket->id] ?? null; @endphp
                            @if ($sla)
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $sla['classes'] }}">{{ $sla['label'] }}</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $ticket->last_reply_at?->diffForHumans() ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.internal-tickets.show', $ticket) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-slate-500">No hay tickets internos que coincidan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
