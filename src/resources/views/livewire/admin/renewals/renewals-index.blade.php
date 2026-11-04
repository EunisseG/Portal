<div>
    <h1 class="text-2xl font-semibold text-slate-900">Vencimientos</h1>
    <p class="mt-1 text-sm text-slate-500">Servicios, dominios y complementos próximos a vencer.</p>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <select wire:model.live="window" class="rounded-md border-slate-300 text-sm shadow-sm">
            @foreach ($windowOptions as $value => $label)
                <option value="{{ $value }}">Próximos {{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="typeFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Todos los tipos</option>
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <span class="text-sm text-slate-500">{{ $items->count() }} por vencer</span>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Elemento</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3">Días restantes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php $days = $item->daysLeft(); @endphp
                    <tr wire:key="{{ $item->type }}-{{ $item->model->id }}">
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $item->typeLabel }}</span>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $item->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $item->clientName ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $item->dueDate->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $days <= 1 ? 'bg-red-50 text-red-700' : ($days <= 7 ? 'bg-orange-50 text-orange-700' : 'bg-amber-50 text-amber-700') }}">
                                {{ $days }} {{ $days === 1 ? 'día' : 'días' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Nada por vencer en esta ventana.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
