<div>
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Órdenes de pago</h1>
        <p class="mt-1 text-slate-600">Folios de cobro en línea generados para las facturas. No son el CFDI; son el intento de pago por la pasarela.</p>
    </div>

    {{-- Tarjetas KPI por estado --}}
    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-lg font-semibold text-amber-600">{{ $kpis['pending'] }}</span>
            <span class="text-sm font-medium text-slate-600">Pendiente</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $kpis['paid'] }}</span>
            <span class="text-sm font-medium text-slate-600">Pagada</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['expired'] }}</span>
            <span class="text-sm font-medium text-slate-600">Expirada</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-lg font-semibold text-red-600">{{ $kpis['cancelled'] }}</span>
            <span class="text-sm font-medium text-slate-600">Cancelada</span>
        </div>
        <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['total'] }}</span>
            <span class="text-sm font-medium text-slate-600">Total</span>
        </div>
    </div>

    {{-- Gráfica de distribución de órdenes de pago por estado (barras) --}}
    <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold text-slate-900">Distribución de órdenes de pago por estado</h2>
        @if ($kpis['total'] > 0)
            <div class="mt-4 h-64">
                <canvas data-status-chart
                        data-labels='@json($chart['labels'])'
                        data-values='@json($chart['data'])'></canvas>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-500">Aún no hay órdenes de pago para graficar.</p>
        @endif
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por folio, referencia o cliente..." :hint="false" />
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
                    <th class="px-4 py-3">Folio</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Factura</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Vence</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr wire:key="po-{{ $order->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $order->folio }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->client?->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->invoice?->folio ?: ($order->invoice ? '#'.$order->invoice->id : '—') }}</td>
                        <td class="px-4 py-3 text-right text-slate-800">{{ $order->amount()->format() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $order->status?->badgeClasses() }}">
                                {{ $order->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $order->expires_at?->format('Y-m-d H:i') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">No hay órdenes de pago que coincidan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
