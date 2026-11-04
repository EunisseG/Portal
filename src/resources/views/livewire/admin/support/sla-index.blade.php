<div>
    {{-- Encabezado + acciones --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">SLA / Soporte</h1>
            <p class="mt-1 text-sm text-slate-500">Métricas de atención: primera respuesta, resolución, cumplimiento de SLA y tickets vencidos, por criticidad.</p>
        </div>
    </div>

    {{-- Pestañas principales: Métricas / Políticas (cambian la vista sin salir) --}}
    <div class="mt-4 border-b border-slate-200">
        <nav class="-mb-px flex gap-6 text-sm font-medium">
            <button type="button" wire:click="setTab('metricas')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'metricas' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Métricas
            </button>
            @if ($canManagePolicies)
                <button type="button" wire:click="setTab('politicas')"
                        class="border-b-2 px-1 pb-3 {{ $tab === 'politicas' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    Políticas
                </button>
            @endif
        </nav>
    </div>

    @if ($tab === 'politicas' && $canManagePolicies)
        <div class="mt-6">
            <livewire:admin.support.sla-policy-index />
        </div>
    @else
    {{-- Pestañas: tipo de ticket (clientes / internos) --}}
    <div class="mt-4 flex gap-2">
        <button wire:click="setType('client')"
                class="rounded-md px-3 py-1.5 text-sm font-medium {{ $ticketType === 'client' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
            Tickets de clientes
        </button>
        <button wire:click="setType('internal')"
                class="rounded-md px-3 py-1.5 text-sm font-medium {{ $ticketType === 'internal' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
            Tickets internos
        </button>
    </div>

    {{-- Filtro por rango de fechas (por fecha de creación del ticket) --}}
    <div class="mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">Desde</label>
                <input type="date" wire:model="from" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Hasta</label>
                <input type="date" wire:model="to" class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <button type="button" wire:click="applyFilter"
                    wire:loading.attr="disabled" wire:target="applyFilter"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="applyFilter">Aplicar</span>
                <span wire:loading wire:target="applyFilter">Calculando…</span>
            </button>
            <button type="button" wire:click="resetFilter" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Últimos 3 meses</button>
            <span class="text-xs text-slate-500">Se miden los tickets creados en el periodo.</span>
        </div>
    </div>

    {{-- KPIs globales --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold text-slate-900">{{ $kpis['total'] }}</div>
            <div class="text-xs text-slate-500">Tickets en el periodo</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold text-slate-900">{{ $kpis['frt_avg'] }}</div>
            <div class="text-xs text-slate-500">Primera respuesta (prom.)</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold text-slate-900">{{ $kpis['res_avg'] }}</div>
            <div class="text-xs text-slate-500">Resolución (prom.)</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold {{ ($kpis['res_compliance'] ?? 100) >= 90 ? 'text-green-600' : (($kpis['res_compliance'] ?? 0) >= 70 ? 'text-amber-600' : 'text-red-600') }}">
                {{ $kpis['res_compliance'] !== null ? $kpis['res_compliance'].'%' : '—' }}
            </div>
            <div class="text-xs text-slate-500">Cumplimiento resolución</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold {{ $kpis['breached'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $kpis['breached'] }}</div>
            <div class="text-xs text-slate-500">Incumplimientos (vencidos)</div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-lg font-semibold text-slate-900">{{ $csatAverage > 0 ? number_format($csatAverage, 2).' / 5' : '—' }}</div>
            <div class="text-xs text-slate-500">CSAT ({{ $csatAnswered }} resp.) ·
                <a href="{{ route('admin.satisfaction.index') }}" wire:navigate class="text-indigo-600 hover:underline">ver</a>
            </div>
        </div>
    </div>

    {{-- Gráfica: % cumplimiento de resolución por criticidad --}}
    <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Cumplimiento de resolución por criticidad (%)</h2>
        @if ($chart['total'] > 0)
            <div class="mt-4 h-64" wire:key="sla-chart-{{ $from }}-{{ $to }}">
                <canvas data-status-chart
                        data-labels='@json($chart['labels'])'
                        data-values='@json($chart['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">No hay tickets en este periodo para calcular métricas.</p>
        @endif
    </div>

    {{-- Detalle por criticidad --}}
    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Criticidad</th>
                    <th class="px-4 py-3">Tickets</th>
                    <th class="px-4 py-3">1ª respuesta (prom.)</th>
                    <th class="px-4 py-3">Resolución (prom.)</th>
                    <th class="px-4 py-3">Cumpl. respuesta</th>
                    <th class="px-4 py-3">Cumpl. resolución</th>
                    <th class="px-4 py-3">Vencidos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $row['label'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['total'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['frt_avg'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['res_avg'] }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['frt_compliance'] !== null ? $row['frt_compliance'].'%' : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $row['res_compliance'] !== null ? $row['res_compliance'].'%' : '—' }}</td>
                        <td class="px-4 py-3 {{ $row['breached'] > 0 ? 'text-red-600' : 'text-slate-600' }}">{{ $row['breached'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
