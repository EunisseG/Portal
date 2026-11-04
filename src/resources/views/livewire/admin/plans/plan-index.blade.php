<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Planes</h1>
        @can('create', App\Domain\Services\Models\Plan::class)
            <a href="{{ route('admin.plans.create') }}"
               class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Nuevo plan
            </a>
        @endcan
    </div>

    <p class="mt-1 text-sm text-slate-500">Catálogo de planes. Cada plan define precios por ciclo (unitario, IVA y total).</p>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por nombre..." :hint="false" />
        <select wire:model.live="typeFilter"
                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
            <option value="">Todos los tipos</option>
            @foreach ($typeOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Ciclos / precios</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $plan->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $plan->type?->label() }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($plan->prices as $price)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                        {{ $price->billing_cycle?->label() }}: {{ $price->total()->format() }}
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400">Sin precios</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($plan->is_active)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', App\Domain\Services\Models\Plan::class)
                                    <a href="{{ route('admin.plans.edit', $plan) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                                @can('delete', App\Domain\Services\Models\Plan::class)
                                    <button type="button" wire:click="delete({{ $plan->id }})"
                                            wire:confirm="¿Eliminar este plan?"
                                            class="text-red-600 hover:underline">Eliminar</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay planes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $plans->links() }}</div>
</div>
