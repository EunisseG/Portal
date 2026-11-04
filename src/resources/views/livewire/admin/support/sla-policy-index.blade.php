<div>
    <h1 class="text-2xl font-semibold text-slate-900">Políticas de SLA</h1>
    <p class="mt-1 text-slate-600">Tiempos objetivo (en minutos) de primera respuesta y de resolución, según la criticidad del ticket. Configurables por separado para tickets de clientes y tickets internos. Se miden 24/7.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    @foreach ([['Tickets de clientes', $clientPolicies], ['Tickets internos', $internalPolicies]] as [$sectionTitle, $policies])
        <h2 class="mt-8 text-lg font-semibold text-slate-900">{{ $sectionTitle }}</h2>
        <div class="mt-3 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Criticidad</th>
                        <th class="px-4 py-3">Primera respuesta</th>
                        <th class="px-4 py-3">Resolución</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($policies as $policy)
                        <tr wire:key="sla-{{ $policy->id }}">
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $policy->criticality?->badgeClasses() }}">
                                    {{ $policy->criticality?->label() }}
                                </span>
                            </td>
                            @if ($editingId === $policy->id)
                                <td class="px-4 py-3">
                                    <input type="number" min="1" wire:model="first_response_minutes"
                                           class="w-28 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-1 text-xs text-slate-400">min</span>
                                    @error('first_response_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" min="1" wire:model="resolution_minutes"
                                           class="w-28 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-1 text-xs text-slate-400">min</span>
                                    @error('resolution_minutes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="save"
                                                wire:loading.attr="disabled" wire:target="save"
                                                class="text-indigo-600 hover:underline disabled:opacity-50">Guardar</button>
                                        <button wire:click="cancel" class="text-slate-500 hover:underline">Cancelar</button>
                                    </div>
                                </td>
                            @else
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $policy->first_response_minutes }} min
                                    <span class="text-xs text-slate-400">({{ round($policy->first_response_minutes / 60, 1) }} h)</span>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $policy->resolution_minutes }} min
                                    <span class="text-xs text-slate-400">({{ round($policy->resolution_minutes / 60, 1) }} h)</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end">
                                        <button wire:click="edit({{ $policy->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay políticas. Ejecuta el seeder SlaPoliciesSeeder.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</div>
