<div>
    <h1 class="text-2xl font-semibold text-slate-900">Complementos</h1>
    <p class="mt-1 text-slate-600">Todos los complementos contratados, agrupados por servicio.</p>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por complemento o servicio..." :hint="false" />
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
                    <th class="px-4 py-3">Complemento</th>
                    <th class="px-4 py-3">Servicio</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3 text-right">Precio</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($addons as $addon)
                    <tr wire:key="addon-{{ $addon->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $addon->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $addon->service?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $addon->service?->client?->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-slate-800">{{ $addon->price()->format() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $addon->status?->badgeClasses() }}">
                                {{ $addon->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $addon->expires_at?->format('Y-m-d') ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($addon->service)
                                <a href="{{ route('admin.services.show', $addon->service) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Ver servicio</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay complementos que coincidan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $addons->links() }}
    </div>
</div>
