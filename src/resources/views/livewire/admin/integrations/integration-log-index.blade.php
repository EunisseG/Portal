<div>
    <h1 class="text-2xl font-semibold text-slate-900">Registros de integración</h1>
    <p class="mt-1 text-slate-600">Operaciones salientes y entrantes. No se registran credenciales.</p>

    <div class="mt-4 flex flex-wrap gap-3">
        <select wire:model.live="integrationFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Todas las integraciones</option>
            @foreach ($integrations as $integration)
                <option value="{{ $integration->id }}">{{ $integration->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="successFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Todos los estados</option>
            <option value="ok">Éxito</option>
            <option value="fail">Error</option>
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Integración</th>
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Dirección</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">HTTP</th>
                    <th class="px-4 py-3">Referencias</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $log->integration?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ \App\Integrations\Registry\IntegrationFieldCatalog::eventLabel($log->event) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $log->direction === 'inbound' ? 'Entrante' : 'Saliente' }}</td>
                        <td class="px-4 py-3">
                            @if ($log->success)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">OK</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700" title="{{ $log->message }}">Error</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $log->http_status ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-400">
                            {{ $log->internal_reference ?: '' }}
                            @if ($log->external_reference) ↔ {{ $log->external_reference }} @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay registros de integración.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
