<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-semibold text-slate-900">Áreas internas</h1>
    <p class="mt-1 text-slate-600">Áreas de la empresa a las que los empleados dirigen sus tickets internos.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar área' : 'Nueva área' }}</h2>
            <div>
                <label class="block text-sm font-medium text-slate-700">Nombre</label>
                <input type="text" wire:model="name"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Correos de notificación</label>
                <textarea wire:model="notify_emails" rows="2" placeholder="agente1@wortev.com, agente2@wortev.com"
                          class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                <p class="mt-1 text-xs text-slate-500">A quién avisar cuando se levante un ticket para esta área. Varios correos separados por coma.</p>
                @error('notify_emails') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                Activa
            </label>
            <div class="flex items-center gap-2">
                <button type="submit"
                        wire:loading.attr="disabled" wire:target="save"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Guardar' : 'Crear' }}</span>
                    <span wire:loading wire:target="save">Guardando…</span>
                </button>
                @if ($editingId)
                    <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                @endif
            </div>
        </form>

        {{-- Listado --}}
        <div class="lg:col-span-2 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Área</th>
                        <th class="px-4 py-3">Correos de notificación</th>
                        <th class="px-4 py-3">Empleados</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($areas as $area)
                        <tr wire:key="area-{{ $area->id }}" wire:loading.class="opacity-50" wire:target="edit({{ $area->id }}),delete({{ $area->id }})">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $area->name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                @php $emails = $area->notifyEmailList(); @endphp
                                {{ count($emails) > 0 ? implode(', ', $emails) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $area->employees_count }}</td>
                            <td class="px-4 py-3">
                                @if ($area->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activa</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="edit({{ $area->id }})"
                                            wire:loading.attr="disabled" wire:target="edit({{ $area->id }})"
                                            class="text-indigo-600 hover:underline disabled:opacity-50">Editar</button>
                                    <button wire:click="delete({{ $area->id }})"
                                            wire:confirm="¿Eliminar esta área? Esta acción no se puede deshacer."
                                            wire:loading.attr="disabled" wire:target="delete({{ $area->id }})"
                                            class="text-red-600 hover:underline disabled:opacity-50">Borrar</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no hay áreas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
