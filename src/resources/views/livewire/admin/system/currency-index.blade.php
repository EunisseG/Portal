<div>
    <h1 class="text-2xl font-semibold text-slate-900">Tipos de moneda</h1>
    <p class="mt-1 text-slate-600">Catálogo de monedas del sistema.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar moneda' : 'Nueva moneda' }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Código (ISO)</label>
                        <input type="text" wire:model="code" maxlength="3" placeholder="MXN"
                               class="mt-1 block w-full rounded-md border-slate-300 uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nombre</label>
                        <input type="text" wire:model="name" placeholder="Peso mexicano"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Símbolo</label>
                        <input type="text" wire:model="symbol" maxlength="8" placeholder="$"
                               class="mt-1 block w-28 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('symbol') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Activa
                    </label>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled" wire:target="save"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Guardar cambios' : 'Crear moneda' }}</span>
                        <span wire:loading wire:target="save">Guardando…</span>
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="cancel"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            Cancelar
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- Listado --}}
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Código</th>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Símbolo</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($currencies as $currency)
                            <tr wire:key="currency-{{ $currency->id }}">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $currency->code }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $currency->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $currency->symbol }}</td>
                                <td class="px-4 py-3">
                                    @if ($currency->is_active)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activa</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $currency->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        <button wire:click="delete({{ $currency->id }})"
                                                wire:confirm="¿Eliminar esta moneda?"
                                                class="text-red-600 hover:underline">Borrar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no hay monedas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
