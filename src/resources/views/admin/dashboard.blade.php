@extends('layouts.app', ['area' => 'admin'])

@section('title', 'Escritorio')

@php
    use App\Domain\Audit\Enums\AuditAction;

    $statLabels = [
        'income' => 'Ingresos',
        'clients' => 'Clientes',
        'orders' => 'Pedidos',
        'invoices' => 'Facturas',
        'tickets' => 'Tickets',
    ];
    $periodLabels = [
        'today' => 'Hoy',
        'yesterday' => 'Ayer',
        'this_month' => 'Este mes',
        'last_month' => 'Mes pasado',
        'total' => 'Total',
    ];
@endphp

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">Escritorios</h1>

    {{-- Tarjetas KPI --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Clientes --}}
        <a href="{{ route('admin.clients.index') }}" class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 hover:ring-indigo-500/40">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-500 text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
            </span>
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $cards['clients']['total'] }} Clientes</div>
                <div class="text-sm text-slate-500">{{ $cards['clients']['suspended'] }} Suspendidos</div>
            </div>
        </a>

        {{-- Pedidos --}}
        <a href="{{ route('admin.services.index') }}" class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 hover:ring-indigo-500/40">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-green-500 text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>
            </span>
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $cards['orders']['total'] }} Pedidos</div>
                <div class="text-sm text-slate-500">{{ $cards['orders']['suspended'] }} Suspendidos</div>
            </div>
        </a>

        {{-- Facturas --}}
        <a href="{{ route('admin.invoices.index', ['pending' => 1]) }}" class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 hover:ring-indigo-500/40">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-red-500 text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
            </span>
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $cards['invoices']['total'] }} Facturas</div>
                <div class="text-sm text-slate-500">{{ $cards['invoices']['unpaid'] }} No pagadas</div>
            </div>
        </a>

        {{-- Tickets --}}
        <a href="{{ route('admin.tickets.index') }}" class="flex items-center gap-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 hover:ring-indigo-500/40">
            <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-amber-500 text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
            </span>
            <div>
                <div class="text-lg font-semibold text-slate-900">{{ $cards['tickets']['total'] }} Tickets</div>
                <div class="text-sm text-slate-500">{{ $cards['tickets']['open'] }} En espera</div>
            </div>
        </a>
    </div>

    {{-- Gráficas de tickets --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Tickets de clientes por estado</h2>
            <div class="mt-4 h-64">
                <canvas id="ticketsByStatusClientChart"
                        data-labels='@json($ticketsByStatusClient['labels'])'
                        data-values='@json($ticketsByStatusClient['data'])'
                        data-colors='@json($ticketsByStatusClient['colors'])'></canvas>
            </div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Tickets internos por estado</h2>
            <div class="mt-4 h-64">
                <canvas id="ticketsByStatusInternalChart"
                        data-labels='@json($ticketsByStatusInternal['labels'])'
                        data-values='@json($ticketsByStatusInternal['data'])'
                        data-colors='@json($ticketsByStatusInternal['colors'])'></canvas>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Tickets creados (últimos 30 días)</h2>
        <div class="mt-4 h-64">
            <canvas id="ticketsByDayChart"
                    data-labels='@json($ticketsByDayClient['labels'])'
                    data-client='@json($ticketsByDayClient['data'])'
                    data-internal='@json($ticketsByDayInternal['data'])'></canvas>
        </div>
    </div>

    {{-- Resumen de satisfacción (CSAT) --}}
    <a href="{{ route('admin.satisfaction.index') }}"
       class="mt-6 flex flex-wrap items-center gap-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 hover:ring-indigo-500/40">
        <div>
            <div class="text-sm text-slate-500">Satisfacción del cliente</div>
            <div class="mt-1 text-3xl font-semibold text-slate-900">
                {{ number_format($csat['average'], 2) }}<span class="text-lg text-slate-400"> / 5</span>
            </div>
        </div>
        <div class="flex items-center gap-6 text-sm">
            <div>
                <div class="text-2xl font-semibold text-green-700">{{ $csat['answered'] }}</div>
                <div class="text-slate-500">Respondidas</div>
            </div>
            <div>
                <div class="text-2xl font-semibold text-amber-600">{{ $csat['pending'] }}</div>
                <div class="text-slate-500">Pendientes</div>
            </div>
        </div>
        <span class="ml-auto text-sm text-indigo-600">Ver detalle →</span>
    </a>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof window.Chart === 'undefined') {
                    return;
                }

                const renderDoughnut = function (id) {
                    const canvas = document.getElementById(id);
                    if (! canvas) {
                        return;
                    }
                    const values = JSON.parse(canvas.dataset.values);
                    if (values.reduce((a, b) => a + b, 0) === 0) {
                        canvas.parentElement.innerHTML = '<div class="flex h-full items-center justify-center text-sm text-slate-400">Sin datos aún.</div>';
                        return;
                    }
                    new window.Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: JSON.parse(canvas.dataset.labels),
                            datasets: [{
                                data: values,
                                backgroundColor: JSON.parse(canvas.dataset.colors),
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });
                };

                renderDoughnut('ticketsByStatusClientChart');
                renderDoughnut('ticketsByStatusInternalChart');

                const dayCanvas = document.getElementById('ticketsByDayChart');
                if (dayCanvas) {
                    new window.Chart(dayCanvas, {
                        type: 'line',
                        data: {
                            labels: JSON.parse(dayCanvas.dataset.labels),
                            datasets: [
                                {
                                    label: 'Clientes',
                                    data: JSON.parse(dayCanvas.dataset.client),
                                    borderColor: '#6366f1',
                                    backgroundColor: 'rgba(99,102,241,0.15)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 2,
                                },
                                {
                                    label: 'Internos',
                                    data: JSON.parse(dayCanvas.dataset.internal),
                                    borderColor: '#f59e0b',
                                    backgroundColor: 'rgba(245,158,11,0.15)',
                                    fill: true,
                                    tension: 0.3,
                                    pointRadius: 2,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                }
            });
        </script>
    @endpush

    {{-- Estadísticas --}}
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Estadísticas</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3"></th>
                        @foreach ($periodLabels as $plabel)
                            <th class="px-5 py-3 text-right">{{ $plabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($statLabels as $rowKey => $rowLabel)
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-700">{{ $rowLabel }}</td>
                            @foreach ($periodLabels as $pKey => $plabel)
                                <td class="px-5 py-3 text-right text-slate-600">{{ $statistics[$rowKey][$pKey] }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Facturas recientes --}}
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Facturas recientes</h2>
            <a href="{{ route('admin.invoices.index') }}" class="text-sm text-indigo-600 hover:underline">Ver todas</a>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Folio</th>
                    <th class="px-5 py-3">Cliente</th>
                    <th class="px-5 py-3 text-right">Cantidad</th>
                    <th class="px-5 py-3">Creado</th>
                    <th class="px-5 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentInvoices as $invoice)
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="font-medium text-indigo-600 hover:underline">{{ $invoice->folio ?: ('#'.$invoice->id) }}</a>
                        </td>
                        <td class="px-5 py-3 text-slate-600">{{ $invoice->client->user->name }}</td>
                        <td class="px-5 py-3 text-right text-slate-800">{{ $invoice->total()->format() }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $invoice->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">{{ $invoice->status?->label() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">No hay facturas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- Últimos pedidos --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Últimos pedidos</h2></div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Cliente</th><th class="px-5 py-3">Servicio</th><th class="px-5 py-3">Estado</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td class="px-5 py-3 text-slate-600">{{ $order->client->user->name }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.services.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->name }}</a>
                            </td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $order->status?->badgeClasses() }}">{{ $order->status?->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500">No hay pedidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Ventas por producto --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <div class="border-b border-slate-100 px-5 py-3"><h2 class="font-semibold text-slate-900">Ventas por producto</h2></div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Producto / Servicio</th><th class="px-5 py-3 text-right">Pedidos</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($productSales as $product)
                        <tr>
                            <td class="px-5 py-3 text-slate-700">{{ $product->name }}</td>
                            <td class="px-5 py-3 text-right text-slate-800">{{ $product->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-6 text-center text-slate-500">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actividad reciente --}}
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Actividad reciente</h2>
            @can('audit.view')
                <a href="{{ route('admin.audit.index') }}" class="text-sm text-indigo-600 hover:underline">Ver auditoría</a>
            @endcan
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-3">Usuario</th><th class="px-5 py-3">Acción</th><th class="px-5 py-3">Fecha</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recentActivity as $audit)
                    @php $action = AuditAction::tryFrom($audit->action); @endphp
                    <tr>
                        <td class="px-5 py-3 text-slate-600">{{ $audit->user?->name ?? 'Sistema' }}</td>
                        <td class="px-5 py-3 text-slate-700">{{ $action?->label() ?? $audit->action }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $audit->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-6 text-center text-slate-500">Sin actividad.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
