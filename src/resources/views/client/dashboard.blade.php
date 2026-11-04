@extends('layouts.app', ['area' => 'client'])

@section('title', 'Escritorio')

@section('content')
    @if ($summary)
        @php
            $firstName = \Illuminate\Support\Str::of($summary['profile']['name'])->trim()->explode(' ')->first();
            $servicesOther = max(0, $summary['services']['total'] - $summary['services']['active'] - $summary['services']['expiring_soon']);
            $invoicesOther = max(0, $summary['invoices']['total'] - $summary['invoices']['paid'] - $summary['invoices']['unpaid']);
            $needsAttention = $summary['invoices']['unpaid'] + $summary['services']['expiring_soon'] + $summary['tickets']['pending'];
        @endphp

        {{-- ============================ HERO ============================ --}}
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 to-indigo-800 text-white shadow-sm">
            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <p class="text-sm font-medium text-indigo-200">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Hola, {{ $firstName }} 👋</h1>
                    <p class="mt-1 text-indigo-100">
                        @if (! empty($summary['profile']['company']))
                            {{ $summary['profile']['company'] }} ·
                        @endif
                        Cliente #{{ $summary['profile']['client_id'] }}
                    </p>

                    @if ($needsAttention > 0)
                        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-medium ring-1 ring-white/20">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94l-8.4 14.55A1.5 1.5 0 0 0 3.24 21h17.52a1.5 1.5 0 0 0 1.3-2.51L13.66 3.94a1.5 1.5 0 0 0-2.6 0Z"/></svg>
                            Tienes {{ $needsAttention }} {{ $needsAttention === 1 ? 'asunto' : 'asuntos' }} que requieren tu atención
                        </p>
                    @else
                        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-sm font-medium ring-1 ring-white/20">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            Todo en orden. No hay pendientes.
                        </p>
                    @endif
                </div>

                {{-- Acciones rápidas --}}
                <div class="flex flex-wrap gap-2 sm:flex-col sm:items-stretch">
                    <a href="{{ route('client.tickets.create') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Abrir ticket
                    </a>
                    <a href="{{ route('client.invoices.index') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-500/40 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-indigo-500/60">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>
                        Mis facturas
                    </a>
                </div>
            </div>
        </section>

        {{-- ==================== Saldo a favor (solo lectura) =================== --}}
        @if (! empty($summary['credit']))
            <div class="mt-6 flex items-center justify-between rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $summary['credit']['has_balance'] ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-50 text-slate-400' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                    <div>
                        <div class="text-sm text-slate-500">Saldo a favor</div>
                        <div class="text-2xl font-semibold {{ $summary['credit']['has_balance'] ? 'text-emerald-600' : 'text-slate-900' }}">{{ $summary['credit']['balance'] }}</div>
                    </div>
                </div>
                <p class="hidden max-w-xs text-right text-xs text-slate-400 sm:block">Crédito disponible en tu cuenta. Lo gestiona nuestro equipo.</p>
            </div>
        @endif

        {{-- ======================= KPIs (métricas) ====================== --}}
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Servicios activos --}}
            <a href="{{ route('client.services.index') }}"
               class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-200">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-green-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76V6a2.25 2.25 0 0 1 2.25-2.25h15A2.25 2.25 0 0 1 21.75 6v6.76M2.25 12.76 12 17.25l9.75-4.49M2.25 12.76V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-5.24"/></svg>
                    </span>
                    <svg class="h-4 w-4 text-slate-300 transition group-hover:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>
                <div class="mt-4 text-3xl font-semibold text-slate-900">{{ $summary['services']['active'] }}</div>
                <div class="text-sm text-slate-500">Servicios activos</div>
            </a>

            {{-- Servicios por vencer --}}
            <a href="{{ route('client.services.index') }}"
               class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-200">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $summary['services']['expiring_soon'] > 0 ? 'bg-amber-50 text-amber-600' : 'bg-slate-50 text-slate-400' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </span>
                    <svg class="h-4 w-4 text-slate-300 transition group-hover:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>
                <div class="mt-4 text-3xl font-semibold {{ $summary['services']['expiring_soon'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $summary['services']['expiring_soon'] }}</div>
                <div class="text-sm text-slate-500">Por vencer (30 días)</div>
            </a>

            {{-- Facturas por pagar --}}
            <a href="{{ route('client.invoices.index') }}"
               class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-200">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $summary['invoices']['unpaid'] > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-400' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3M3.75 5.25h16.5A1.5 1.5 0 0 1 21.75 6.75v10.5a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
                    </span>
                    <svg class="h-4 w-4 text-slate-300 transition group-hover:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>
                <div class="mt-4 text-3xl font-semibold {{ $summary['invoices']['unpaid'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $summary['invoices']['unpaid'] }}</div>
                <div class="text-sm text-slate-500">Facturas por pagar</div>
            </a>

            {{-- Tickets abiertos --}}
            <a href="{{ route('client.tickets.index') }}"
               class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-200">
                <div class="flex items-center justify-between">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $summary['tickets']['open'] > 0 ? 'bg-blue-50 text-blue-600' : 'bg-slate-50 text-slate-400' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 0 1-2.555-.337A5.97 5.97 0 0 1 5.41 20.97a5.97 5.97 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/></svg>
                    </span>
                    <svg class="h-4 w-4 text-slate-300 transition group-hover:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>
                <div class="mt-4 text-3xl font-semibold {{ $summary['tickets']['open'] > 0 ? 'text-blue-600' : 'text-slate-900' }}">{{ $summary['tickets']['open'] }}</div>
                <div class="text-sm text-slate-500">Tickets abiertos</div>
            </a>
        </div>

        {{-- ==================== LISTAS + GRÁFICAS ==================== --}}
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Columna principal (2/3): listas --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Servicios próximos a vencer --}}
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">Próximos a vencer</h2>
                        <a href="{{ route('client.services.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">Ver todos</a>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        @forelse ($summary['upcoming_services'] as $service)
                            @php
                                $days = $service->expires_at ? (int) now()->startOfDay()->diffInDays($service->expires_at->copy()->startOfDay(), false) : null;
                                $urgent = $days !== null && $days <= 7;
                            @endphp
                            <li class="flex items-center justify-between px-5 py-3.5">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-800">{{ $service->name }}</p>
                                    <p class="mt-0.5 text-xs {{ $urgent ? 'font-medium text-amber-600' : 'text-slate-500' }}">
                                        Vence el {{ $service->expires_at?->translatedFormat('d M Y') }}
                                        @if ($days !== null)
                                            · {{ $days <= 0 ? 'hoy' : ('en '.$days.' '.($days === 1 ? 'día' : 'días')) }}
                                        @endif
                                    </p>
                                </div>
                                <span class="ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">{{ $service->status?->label() }}</span>
                            </li>
                        @empty
                            <li class="flex flex-col items-center px-5 py-10 text-center">
                                <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                <p class="mt-3 text-sm text-slate-500">No tienes servicios por vencer en los próximos 30 días.</p>
                            </li>
                        @endforelse
                    </ul>
                </div>

                {{-- Facturas recientes --}}
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h2 class="font-semibold text-slate-900">Facturas recientes</h2>
                        <a href="{{ route('client.invoices.index') }}" class="text-sm font-medium text-indigo-600 hover:underline">Ver todas</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3">Folio</th>
                                    <th class="px-5 py-3 text-right">Monto</th>
                                    <th class="px-5 py-3">Emitida</th>
                                    <th class="px-5 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($summary['recent_invoices'] as $invoice)
                                    <tr class="transition hover:bg-slate-50/70">
                                        <td class="px-5 py-3.5 font-medium text-slate-700">{{ $invoice->folio ?: ('#'.$invoice->id) }}</td>
                                        <td class="px-5 py-3.5 text-right font-medium text-slate-800">{{ $invoice->total()->format() }}</td>
                                        <td class="px-5 py-3.5 text-slate-600">{{ $invoice->issued_at?->translatedFormat('d M Y') }}</td>
                                        <td class="px-5 py-3.5">
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">{{ $invoice->status?->label() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No tienes facturas registradas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Columna lateral (1/3): gráficas + perfil --}}
            <div class="space-y-6">
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="font-semibold text-slate-900">Tus servicios</h2>
                    <div class="mt-4 h-52">
                        <canvas id="clientServicesChart"
                                data-labels='@json(['Activos', 'Por vencer', 'Otros'])'
                                data-values='@json([$summary['services']['active'], $summary['services']['expiring_soon'], $servicesOther])'
                                data-colors='@json(['#22c55e', '#f59e0b', '#94a3b8'])'></canvas>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="font-semibold text-slate-900">Tus facturas</h2>
                    <div class="mt-4 h-52">
                        <canvas id="clientInvoicesChart"
                                data-labels='@json(['Pagadas', 'No pagadas', 'Otras'])'
                                data-values='@json([$summary['invoices']['paid'], $summary['invoices']['unpaid'], $invoicesOther])'
                                data-colors='@json(['#22c55e', '#ef4444', '#94a3b8'])'></canvas>
                    </div>
                </div>

                {{-- Perfil resumido --}}
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Tu perfil</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Nombre</dt>
                            <dd class="truncate text-right font-medium text-slate-800">{{ $summary['profile']['name'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Correo</dt>
                            <dd class="truncate text-right font-medium text-slate-800">{{ $summary['profile']['email'] }}</dd>
                        </div>
                        @if (! empty($summary['profile']['company']))
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-500">Empresa</dt>
                                <dd class="truncate text-right font-medium text-slate-800">{{ $summary['profile']['company'] }}</dd>
                            </div>
                        @endif
                    </dl>
                    <a href="{{ route('profile.edit') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline">
                        Editar perfil
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </div>

                {{-- Ejecutivo(s) de ventas que llevan la cuenta (uno por servicio) --}}
                @if (! empty($summary['profile']['sales_reps']))
                    @php $reps = $summary['profile']['sales_reps']; @endphp
                    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">
                            {{ count($reps) > 1 ? 'Tus ejecutivos de ventas' : 'Tu ejecutivo de ventas' }}
                        </h2>
                        <div class="mt-3 space-y-3">
                            @foreach ($reps as $rep)
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                                        {{ strtoupper(mb_substr($rep['name'], 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-slate-800">{{ $rep['name'] }}</div>
                                        @if (! empty($rep['email']))
                                            <a href="mailto:{{ $rep['email'] }}" class="block truncate text-sm text-indigo-600 hover:underline">{{ $rep['email'] }}</a>
                                        @endif
                                        @if (! empty($rep['phone']))
                                            <div class="truncate text-sm text-slate-500">{{ $rep['phone'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @push('scripts')
            <script>
                (function () {
                    function renderDoughnut(id) {
                        if (typeof window.Chart === 'undefined') return;
                        const canvas = document.getElementById(id);
                        if (!canvas || canvas.dataset.rendered) return;

                        const values = JSON.parse(canvas.dataset.values);
                        if (values.reduce((a, b) => a + b, 0) === 0) {
                            const parent = canvas.parentElement;
                            parent.innerHTML = '<div class="flex h-full items-center justify-center text-sm text-slate-400">Sin datos aún.</div>';
                            return;
                        }

                        canvas.dataset.rendered = '1';
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
                                cutout: '62%',
                                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } } },
                            },
                        });
                    }

                    function renderClientCharts() {
                        renderDoughnut('clientServicesChart');
                        renderDoughnut('clientInvoicesChart');
                    }

                    document.addEventListener('DOMContentLoaded', renderClientCharts);
                    document.addEventListener('livewire:navigated', renderClientCharts);
                })();
            </script>
        @endpush
    @else
        <h1 class="text-2xl font-semibold text-slate-900">Escritorio</h1>
        <div class="mt-6 rounded-xl bg-white p-8 text-center text-slate-500 shadow-sm ring-1 ring-slate-900/5">
            Tu cuenta aún no tiene un perfil de cliente asociado. Contacta con WORTEV.
        </div>
    @endif
@endsection
