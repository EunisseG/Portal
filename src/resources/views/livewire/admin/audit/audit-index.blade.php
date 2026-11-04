<div>
    <h1 class="text-2xl font-semibold text-slate-900">Auditoría</h1>
    <p class="mt-1 text-slate-600">Registro de acciones administrativas relevantes.</p>

    <div class="mt-4">
        <select wire:model.live="actionFilter"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todas las acciones</option>
            @foreach ($actionOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Acción</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($audits as $audit)
                    @php $action = App\Domain\Audit\Enums\AuditAction::tryFrom($audit->action); @endphp
                    <tr wire:key="audit-{{ $audit->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $audit->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $audit->user?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                {{ $action?->label() ?? $audit->action }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $audit->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay registros de auditoría.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $audits->links() }}
    </div>
</div>
