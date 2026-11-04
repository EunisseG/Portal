{{-- Resumen del escritorio del cliente. Espera: $summary (array), $readOnly (bool) --}}
@php $readOnly = $readOnly ?? false; @endphp

<div class="grid gap-4 lg:grid-cols-2">
    {{-- Perfil --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Perfil</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-500">Nombre</dt>
                <dd class="font-medium text-slate-800">{{ $summary['profile']['name'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">ID de cliente</dt>
                <dd class="font-medium text-slate-800">#{{ $summary['profile']['client_id'] }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Correo</dt>
                <dd class="font-medium text-slate-800">{{ $summary['profile']['email'] }}</dd>
            </div>
            @if (! empty($summary['profile']['company']))
                <div class="flex justify-between">
                    <dt class="text-slate-500">Empresa</dt>
                    <dd class="font-medium text-slate-800">{{ $summary['profile']['company'] }}</dd>
                </div>
            @endif
            @if (isset($summary['credit']))
                <div class="flex justify-between border-t border-slate-100 pt-2">
                    <dt class="text-slate-500">Saldo a favor</dt>
                    <dd class="font-semibold {{ $summary['credit']['has_balance'] ? 'text-green-700' : 'text-slate-800' }}">{{ $summary['credit']['balance'] }}</dd>
                </div>
            @endif
        </dl>
    </div>

    {{-- Servicios / Pedidos --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Servicios</h2>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-2xl font-semibold text-slate-900">{{ $summary['services']['total'] }}</div>
                <div class="text-xs text-slate-500">Total</div>
            </div>
            <div class="rounded-lg bg-green-50 p-3">
                <div class="text-2xl font-semibold text-green-700">{{ $summary['services']['active'] }}</div>
                <div class="text-xs text-slate-500">Activos</div>
            </div>
            <div class="rounded-lg bg-amber-50 p-3">
                <div class="text-2xl font-semibold text-amber-700">{{ $summary['services']['expiring_soon'] }}</div>
                <div class="text-xs text-slate-500">Por vencer</div>
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-400">Próximos a vencer en los siguientes 30 días.</p>
    </div>

    {{-- Facturas --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Facturas</h2>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-2xl font-semibold text-slate-900">{{ $summary['invoices']['total'] }}</div>
                <div class="text-xs text-slate-500">Total</div>
            </div>
            <div class="rounded-lg bg-green-50 p-3">
                <div class="text-2xl font-semibold text-green-700">{{ $summary['invoices']['paid'] }}</div>
                <div class="text-xs text-slate-500">Pagadas</div>
            </div>
            <div class="rounded-lg bg-red-50 p-3">
                <div class="text-2xl font-semibold text-red-700">{{ $summary['invoices']['unpaid'] }}</div>
                <div class="text-xs text-slate-500">No pagadas</div>
            </div>
        </div>
    </div>

    {{-- Tickets --}}
    <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Tickets</h2>
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-slate-50 p-3">
                <div class="text-2xl font-semibold text-slate-900">{{ $summary['tickets']['total'] }}</div>
                <div class="text-xs text-slate-500">Total</div>
            </div>
            <div class="rounded-lg bg-blue-50 p-3">
                <div class="text-2xl font-semibold text-blue-700">{{ $summary['tickets']['open'] }}</div>
                <div class="text-xs text-slate-500">Abiertos</div>
            </div>
            <div class="rounded-lg bg-amber-50 p-3">
                <div class="text-2xl font-semibold text-amber-700">{{ $summary['tickets']['pending'] }}</div>
                <div class="text-xs text-slate-500">En espera</div>
            </div>
        </div>
    </div>
</div>
