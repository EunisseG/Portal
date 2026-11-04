<div>
    <h1 class="text-2xl font-semibold text-slate-900">Promociones de productos</h1>
    <p class="mt-1 text-slate-600">Catálogo de cupones y descuentos.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar promoción' : 'Nueva promoción' }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Código</label>
                        <input type="text" wire:model="code" placeholder="BIENVENIDA10"
                               class="mt-1 block w-full rounded-md border-slate-300 uppercase shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nombre</label>
                        <input type="text" wire:model="name" placeholder="Descuento de bienvenida"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Tipo</label>
                            <select wire:model="discount_type"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($discountTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Valor</label>
                            <input type="number" wire:model="discount_value" min="1"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('discount_value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Porcentaje: 1 a 100. Monto fijo: en centavos.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Inicia</label>
                            <input type="date" wire:model="starts_at"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Termina</label>
                            <input type="date" wire:model="ends_at"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        Activa
                    </label>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        {{ $editingId ? 'Guardar cambios' : 'Crear promoción' }}
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
                            <th class="px-4 py-3">Descuento</th>
                            <th class="px-4 py-3">Vigencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($promotions as $promotion)
                            <tr wire:key="promo-{{ $promotion->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $promotion->code }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $promotion->name }}</td>
                                <td class="px-4 py-3 text-slate-800">{{ $promotion->discountLabel() }}</td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ $promotion->starts_at?->format('Y-m-d') ?: '—' }} → {{ $promotion->ends_at?->format('Y-m-d') ?: '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($promotion->is_active)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activa</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $promotion->id }})" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</button>
                                        <button wire:click="delete({{ $promotion->id }})"
                                                wire:confirm="¿Eliminar esta promoción?"
                                                class="text-red-600 hover:underline">Borrar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aún no hay promociones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
