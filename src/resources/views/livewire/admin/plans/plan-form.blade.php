<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar plan' : 'Nuevo plan' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nombre del plan</label>
                <input type="text" wire:model="name"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Tipo de servicio</label>
                <select wire:model="type"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Descripción</label>
            <textarea wire:model="description" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="is_active"
                   class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
            Plan activo (disponible al contratar servicios)
        </label>

        {{-- Precios por ciclo --}}
        <div class="border-t border-slate-100 pt-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Precios por ciclo</h2>
            <p class="mt-1 text-xs text-slate-500">Marca los ciclos que ofrece este plan y define su precio. El total se calcula con el IVA.</p>
            @error('enabled') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 space-y-3">
                @foreach ($cycles as $cycle)
                    @php $c = $cycle->value; @endphp
                    <div class="rounded-lg border border-slate-200 p-4" wire:key="cycle-{{ $c }}">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                            <input type="checkbox" wire:model.live="enabled.{{ $c }}"
                                   class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                            {{ $cycle->label() }}
                        </label>

                        @if ($enabled[$c] ?? false)
                            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">Precio unitario (MXN)</label>
                                    <input type="number" step="0.01" min="0" wire:model.live="unitPrice.{{ $c }}"
                                           class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                    @error("unitPrice.$c") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">IVA (%)</label>
                                    <input type="number" step="0.01" min="0" wire:model.live="taxRate.{{ $c }}"
                                           class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                    @error("taxRate.$c") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600">Total</label>
                                    <div class="mt-1 rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-800">
                                        {{ $this->totalFor($c) }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear plan' }}
            </button>
            <a href="{{ route('admin.plans.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
