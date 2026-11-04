@php
    use App\Support\Money;

    // Tarjetas de distribución por estado (barras con conteo).
    $statusCards = [
        ['title' => 'Facturas por estado', 'chart' => $invoiceChart],
        ['title' => 'Órdenes de pago por estado', 'chart' => $paymentOrderChart],
        ['title' => 'Pedidos por estado', 'chart' => $serviceChart],
        ['title' => 'Dominios por estado', 'chart' => $domainChart],
        ['title' => 'Clientes por estado', 'chart' => $clientChart],
        ['title' => 'Tickets de clientes por estado', 'chart' => $ticketChart],
        ['title' => 'Tickets internos por estado', 'chart' => $internalTicketChart],
        ['title' => 'Productos por estado', 'chart' => $productChart],
        ['title' => 'Clientes por ejecutivo', 'chart' => $clientsByRep],
    ];

    $revenueTotal = (new Money((int) $revenue['totalCents']))->format();
    $revenueByRepTotal = (new Money((int) $revenueByRep['total']))->format();
    $salesVsRenewalsTotal = (new Money((int) $salesVsRenewals['total']))->format();
    $salesVsRenewalsSeries = [
        ['label' => 'Ventas nuevas', 'data' => $salesVsRenewals['newSales'], 'color' => '#6366f1'],
        ['label' => 'Renovaciones', 'data' => $salesVsRenewals['renewals'], 'color' => '#22c55e'],
    ];
@endphp

<div class="report-page">
    {{-- Estilos de impresión: oculta la navegación y deja solo el reporte. --}}
    <style>
        @media print {
            header, nav, footer { display: none !important; }
            body { background: #fff !important; }
            .report-no-print { display: none !important; }
            .report-page { padding: 0 !important; }
            /* Evita cortar una gráfica entre dos páginas. */
            .report-card { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>

    {{-- Encabezado + acciones --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Gráficas</h1>
            <p class="mt-1 text-sm text-slate-500">Panorama general del portal: distribución por estado de cada módulo e ingresos por facturas pagadas.</p>
        </div>
        <div class="report-no-print flex items-center gap-2">
            {{-- Exportar el reporte (KPIs + ingresos por mes) respetando el filtro. --}}
            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                <button type="button" @click="open = ! open"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Exportar
                    <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </button>
                <form method="GET" action="{{ route('admin.reports.export') }}" x-show="open" x-cloak x-transition
                      class="absolute right-0 z-20 mt-2 w-64 space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Exportar reporte</p>
                    {{-- Rango actual del filtro (se envía como contexto). --}}
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
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
            <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z"/></svg>
                Imprimir
            </button>
        </div>
    </div>

    {{-- Filtro por rango de fechas --}}
    <div class="report-no-print mt-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">Desde</label>
                <input type="date" wire:model="from"
                       class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Hasta</label>
                <input type="date" wire:model="to"
                       class="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>
            <button type="button" wire:click="applyFilter"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Aplicar
            </button>
            <button type="button" wire:click="resetFilter"
                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Últimos 12 meses
            </button>
            <span class="text-xs text-slate-500">
                Las distribuciones se filtran por fecha de registro; los ingresos, por fecha de pago.
            </span>
        </div>
    </div>

    {{-- Ingresos por facturas pagadas (en el rango seleccionado) --}}
    <div class="report-card mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Ingresos por facturas pagadas</h2>
            <span class="text-sm font-medium text-slate-500">
                Periodo: {{ $from }} a {{ $to }} ·
                Total: <span class="text-slate-900">{{ $revenueTotal }}</span>
            </span>
        </div>
        @if ($revenue['totalCents'] > 0)
            <div class="mt-4 h-72" wire:key="revenue-{{ $from }}-{{ $to }}">
                <canvas data-money-chart
                        data-labels='@json($revenue['labels'])'
                        data-values='@json($revenue['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">No hay pagos registrados en este periodo.</p>
        @endif
    </div>

    {{-- Ingresos por ejecutivo de ventas (dinero cobrado, ventas + renovaciones) --}}
    <div class="report-card mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Ingresos por ejecutivo de ventas</h2>
            <span class="text-sm font-medium text-slate-500">
                Total: <span class="text-slate-900">{{ $revenueByRepTotal }}</span>
            </span>
        </div>
        @if ($revenueByRep['total'] > 0)
            <div class="mt-4 h-72" wire:key="revrep-{{ $from }}-{{ $to }}">
                <canvas data-money-chart
                        data-labels='@json($revenueByRep['labels'])'
                        data-values='@json($revenueByRep['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">No hay ingresos por ejecutivo en este periodo.</p>
        @endif
    </div>

    {{-- Ventas nuevas vs renovaciones por ejecutivo (eventos comerciales, en dinero) --}}
    <div class="report-card mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900">Ventas nuevas vs renovaciones por ejecutivo</h2>
            <span class="text-sm font-medium text-slate-500">
                Total: <span class="text-slate-900">{{ $salesVsRenewalsTotal }}</span>
            </span>
        </div>
        @if (count($salesVsRenewals['labels']) > 0)
            <div class="mt-4 h-72" wire:key="svr-{{ $from }}-{{ $to }}">
                <canvas data-grouped-money-chart
                        data-labels='@json($salesVsRenewals['labels'])'
                        data-series='@json($salesVsRenewalsSeries)'></canvas>
            </div>
            <p class="mt-2 text-xs text-slate-400">
                Nota: las renovaciones solo se contabilizan desde que se activó el registro de eventos.
                Las ventas nuevas incluyen el histórico recuperado. Los dominios no tienen precio modelado (cuentan en 0).
            </p>
        @else
            <p class="mt-3 text-sm text-slate-500">No hay eventos de venta/renovación en este periodo.</p>
        @endif
    </div>

    {{-- Distribuciones por estado --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ($statusCards as $i => $card)
            <div class="report-card rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h2>
                @if (($card['chart']['total'] ?? 0) > 0)
                    <div class="mt-4 h-64" wire:key="chart-{{ $i }}-{{ $from }}-{{ $to }}">
                        <canvas data-status-chart
                                data-labels='@json($card['chart']['labels'])'
                                data-values='@json($card['chart']['data'])'></canvas>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">Sin datos en este periodo.</p>
                @endif
            </div>
        @endforeach
    </div>

    @push('scripts')
        @include('partials.status-bar-chart-script')
        @include('partials.money-bar-chart-script')
        @include('partials.grouped-money-bar-chart-script')
    @endpush
</div>
