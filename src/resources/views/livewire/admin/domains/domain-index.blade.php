<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Dominios</h1>
        @can('create', App\Domain\Services\Models\Domain::class)
            <a href="{{ route('admin.domains.create') }}"
               class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Nuevo dominio
            </a>
        @endcan
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por dominio o cliente..." :hint="false" />
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
                    <th class="px-4 py-3">Dominio</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($domains as $domain)
                    <tr wire:key="domain-{{ $domain->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $domain->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $domain->client->user->name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $domain->status?->badgeClasses() }}">
                                {{ $domain->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $domain->expires_at?->format('Y-m-d') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', App\Domain\Services\Models\Domain::class)
                                    <button type="button" wire:click="startRenew({{ $domain->id }})"
                                            class="text-indigo-600 hover:underline">Renovar</button>
                                    <a href="{{ route('admin.domains.edit', $domain) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                                @can('delete', App\Domain\Services\Models\Domain::class)
                                    <button type="button" wire:click="delete({{ $domain->id }})"
                                            wire:confirm="¿Eliminar este dominio?"
                                            class="text-red-600 hover:underline">Eliminar</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay dominios que coincidan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $domains->links() }}</div>

    {{-- Modal: renovar dominio (elegir años) --}}
    @if ($renewingDomain)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Renovar dominio</h2>
                    <button type="button" wire:click="cancelRenew" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="renew" class="space-y-4 px-5 py-4">
                    <p class="text-sm text-slate-600">
                        <span class="font-medium text-slate-900">{{ $renewingDomain->name }}</span><br>
                        Vence actualmente: {{ $renewingDomain->expires_at?->format('Y-m-d') ?: '—' }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Años a renovar</label>
                        <select wire:model="renewYears" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @for ($y = 1; $y <= 5; $y++)
                                <option value="{{ $y }}">{{ $y }} {{ $y === 1 ? 'año' : 'años' }}</option>
                            @endfor
                        </select>
                        @error('renewYears') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="cancelRenew"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Renovar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
