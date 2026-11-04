<div>
    <h1 class="text-2xl font-semibold text-slate-900">Mantenimientos</h1>
    <p class="mt-1 text-slate-600">Avisos de mantenimiento programado para los clientes.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar mantenimiento' : 'Nuevo mantenimiento' }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Título</label>
                        <input type="text" wire:model="title"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Descripción</label>
                        <textarea wire:model="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Servicio afectado</label>
                        <select wire:model.live="service_type"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">Todos los servicios (global)</option>
                            @foreach ($serviceTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Se avisa solo a los clientes con ese tipo de servicio.</p>
                        @error('service_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- El servidor solo aplica a Hosting --}}
                    @if ($service_type === \App\Domain\Services\Enums\ServiceType::Hosting->value)
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Servidor de hosting</label>
                            <select wire:model="server_id"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Todos los servidores de hosting</option>
                                @foreach ($servers as $server)
                                    <option value="{{ $server->id }}">{{ $server->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-400">Opcional: acota a un servidor concreto.</p>
                            @error('server_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Inicio</label>
                        <input type="datetime-local" wire:model="starts_at"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Fin (opcional)</label>
                        <input type="datetime-local" wire:model="ends_at"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Estado</label>
                        <select wire:model="status"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_published" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        Publicado (visible para clientes)
                    </label>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        {{ $editingId ? 'Guardar cambios' : 'Crear mantenimiento' }}
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
                            <th class="px-4 py-3">Alcance</th>
                            <th class="px-4 py-3">Inicio</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Publicado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($maintenances as $m)
                            <tr wire:key="maint-{{ $m->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $m->title }}</td>
                                <td class="px-4 py-3">
                                    @if ($m->service_type)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $m->service_type->label() }}</span>
                                        @if ($m->server)
                                            <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">{{ $m->server->name }}</span>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">Todos</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $m->starts_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $m->status?->badgeClasses() }}">
                                        {{ $m->status?->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($m->is_published)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Sí</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">No</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        <button wire:click="edit({{ $m->id }})" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</button>
                                        <button wire:click="delete({{ $m->id }})"
                                                wire:confirm="¿Eliminar este mantenimiento?"
                                                class="text-red-600 hover:underline">Borrar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aún no hay mantenimientos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
