<div class="mx-auto max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Panel de {{ $client->user->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">Vista de solo lectura del escritorio del cliente.</p>
        </div>
        <a href="{{ route('admin.clients.show', $client) }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            ← Volver al detalle
        </a>
    </div>

    <div class="mt-4 rounded-md bg-slate-900/5 px-4 py-2 text-sm text-slate-600">
        Estás viendo la información de este cliente como administrador. No estás dentro de su sesión.
    </div>

    <div class="mt-6">
        @include('partials.client-summary', ['summary' => $summary, 'readOnly' => true])
    </div>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.services.index', ['q' => $client->user->name]) }}"
           class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Ver servicios de este cliente
        </a>
        <a href="{{ route('admin.domains.index', ['q' => $client->user->name]) }}"
           class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            Ver dominios de este cliente
        </a>
    </div>
</div>
