<div>
    <h1 class="text-2xl font-semibold text-slate-900">Ejecutivos de ventas</h1>
    <p class="mt-1 text-slate-600">Gestores de cuenta que se asignan a cada servicio. No son usuarios del sistema.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
            {{ session('error') }}
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Formulario --}}
        <div class="lg:col-span-1">
            <form wire:submit="save" wire:key="rep-form-{{ $editingId ?? 'new' }}"
                  class="rounded-xl bg-white p-5 shadow-sm ring-1 {{ $editingId ? 'ring-2 ring-indigo-400' : 'ring-slate-900/5' }}">
                <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar ejecutivo #'.$editingId : 'Nuevo ejecutivo' }}</h2>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nombre</label>
                        <input type="text" wire:model="name" placeholder="Nombre del ejecutivo"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Correo (opcional)</label>
                        <input type="email" wire:model="email" placeholder="ejecutivo@wortev.com"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Teléfono (opcional)</label>
                        <input type="text" wire:model="phone" placeholder="55 1234 5678"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Activo (disponible para asignar)
                    </label>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        {{ $editingId ? 'Guardar cambios' : 'Crear ejecutivo' }}
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
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Correo</th>
                            <th class="px-4 py-3">Teléfono</th>
                            <th class="px-4 py-3">Servicios</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($reps as $rep)
                            <tr wire:key="rep-{{ $rep->id }}">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $rep->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $rep->email ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $rep->phone ?: '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $rep->services_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($rep->is_active)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-3">
                                        @if ($rep->services_count > 0)
                                            <a href="{{ route('admin.clients.index', ['rep' => $rep->id]) }}" wire:navigate
                                               class="text-slate-600 hover:text-slate-900 hover:underline">Ver clientes</a>
                                        @endif
                                        <button wire:click="edit({{ $rep->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                        @if ($rep->services_count > 0)
                                            <button wire:click="startReassign({{ $rep->id }})" class="text-amber-600 hover:underline">Reasignar cartera</button>
                                        @endif
                                        <button wire:click="delete({{ $rep->id }})"
                                                @if ($rep->services_count === 0) wire:confirm="¿Eliminar este ejecutivo?" @endif
                                                class="text-red-600 hover:underline">Borrar</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Aún no hay ejecutivos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Exportar con opciones: rango de fechas (por alta), estado y formato. --}}
            <div class="mt-4 flex justify-end">
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button type="button" @click="open = ! open"
                            class="inline-flex items-center gap-1.5 rounded-md bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Exportar ejecutivos
                        <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <form method="GET" action="{{ route('admin.sales-reps.export') }}" x-show="open" x-cloak x-transition
                          class="absolute right-0 bottom-full z-20 mb-2 w-72 space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Exportar ejecutivos</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500">Desde (alta)</label>
                                <input type="date" name="from" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500">Hasta (alta)</label>
                                <input type="date" name="to" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500">Estado</label>
                            <select name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Todos</option>
                                <option value="active">Activos</option>
                                <option value="inactive">Inactivos</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-500">Formato</label>
                            <select name="format" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="xlsx">Excel (.xlsx)</option>
                                <option value="csv">CSV (.csv)</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Descargar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Panel de reasignación de cartera (al dar de baja un ejecutivo) --}}
    @if ($reassigningRep)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4" wire:click.self="cancelReassign">
            <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Reasignar cartera de {{ $reassigningRep->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">Marca los servicios y elige a qué ejecutivo moverlos. Puedes repartirlos en varias pasadas.</p>
                    </div>
                    <button wire:click="cancelReassign" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                @if ($reassignServices->isEmpty())
                    <div class="mt-5 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                        Este ejecutivo ya no tiene servicios asignados. Puedes eliminarlo.
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button wire:click="cancelReassign"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cerrar</button>
                        <button wire:click="delete({{ $reassigningRep->id }})"
                                wire:confirm="¿Eliminar este ejecutivo?"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Eliminar ejecutivo</button>
                    </div>
                @else
                    {{-- Destino + acción --}}
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[12rem]">
                            <label class="block text-sm font-medium text-slate-700">Mover seleccionados a</label>
                            <select wire:model="reassignTo" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">— Elige ejecutivo —</option>
                                @foreach ($reassignTargets as $target)
                                    <option value="{{ $target->id }}">{{ $target->name }}</option>
                                @endforeach
                            </select>
                            @error('reassignTo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button wire:click="reassignSelected"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Reasignar seleccionados
                        </button>
                    </div>
                    @error('selectedServices') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($reassignTargets->isEmpty())
                        <p class="mt-3 text-sm text-amber-600">No hay otros ejecutivos activos. Crea o activa otro para poder reasignar.</p>
                    @endif

                    <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">
                                        <button type="button" wire:click="toggleAllServices" class="text-indigo-600 hover:underline">Todos</button>
                                    </th>
                                    <th class="px-3 py-2">Servicio</th>
                                    <th class="px-3 py-2">Cliente</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reassignServices as $service)
                                    <tr wire:key="reassign-{{ $service->id }}">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" value="{{ $service->id }}" wire:model="selectedServices"
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        </td>
                                        <td class="px-3 py-2 text-slate-800">{{ $service->name }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $service->client?->user?->name ?? '—' }}{{ $service->client?->company ? ' ('.$service->client->company.')' : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
