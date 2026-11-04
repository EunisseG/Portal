<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar categoría' : 'Nueva categoría' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Nombre</label>
            <input type="text" wire:model="name"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Descripción</label>
            <textarea wire:model="description" rows="3"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Orden</label>
            <input type="number" min="0" wire:model="sort_order"
                   class="mt-1 block w-32 rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear categoría' }}
            </button>
            <a href="{{ route('admin.kb.categories.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
