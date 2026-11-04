<div>
    <h1 class="text-2xl font-semibold text-slate-900">Respuestas preparadas</h1>
    <p class="mt-1 text-slate-600">Plantillas reutilizables para responder tickets más rápido.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar respuesta' : 'Nueva respuesta' }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Título</label>
                        <input type="text" wire:model="title"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Contenido</label>
                        <textarea wire:model="body" rows="6"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Orden</label>
                        <input type="number" wire:model="sort_order" min="0"
                               class="mt-1 block w-28 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('sort_order') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <button type="submit"
                            wire:loading.attr="disabled" wire:target="save"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Guardar cambios' : 'Crear respuesta' }}</span>
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
                            <th class="px-4 py-3">Título</th>
                            <th class="px-4 py-3">Vista previa</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($responses as $response)
                            <tr wire:key="canned-{{ $response->id }}">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $response->title }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Str::limit($response->body, 70) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $response->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        <button wire:click="delete({{ $response->id }})"
                                                wire:confirm="¿Eliminar esta respuesta preparada?"
                                                class="text-red-600 hover:underline">Borrar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-slate-500">Aún no hay respuestas preparadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
