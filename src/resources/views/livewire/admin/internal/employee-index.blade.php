<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Empleados</h1>
            <p class="mt-1 text-slate-600">Personal interno que puede levantar tickets desde el portal de empleados.</p>
        </div>
        @unless ($showForm)
            <button wire:click="newEmployee" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nuevo empleado</button>
        @endunless
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Formulario alta/edición --}}
    @if ($showForm)
        <form wire:submit="save" class="mt-6 space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar empleado' : 'Nuevo empleado' }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nombre</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Apellido</label>
                    <input type="text" wire:model="last_name" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Correo</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <p class="mt-1 text-xs text-slate-500">Aquí recibirá su código de acceso.</p>
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Número de empleado</label>
                    <input type="text" wire:model="employee_number" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('employee_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Área</label>
                    <select wire:model="internal_area_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">— Elige área —</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}{{ $area->is_active ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                    @error('internal_area_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex items-center gap-2 border-t border-slate-100 pt-4">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    {{ $editingId ? 'Guardar cambios' : 'Dar de alta' }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
            </div>
        </form>
    @endif

    {{-- Buscador --}}
    <div class="mt-6">
        <input type="text" wire:model.live.debounce.400ms="search" placeholder="Buscar por nombre, correo o número…"
               class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500 sm:max-w-sm">
    </div>

    {{-- Listado --}}
    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Nº empleado</th>
                    <th class="px-4 py-3">Área</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($employees as $employee)
                    <tr wire:key="emp-{{ $employee->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $employee->email }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $employee->internalArea?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <button wire:click="edit({{ $employee->id }})" class="text-indigo-600 hover:underline">Editar</button>
                                <button wire:click="delete({{ $employee->id }})"
                                        wire:confirm="¿Eliminar a este empleado? Perderá el acceso al portal interno."
                                        class="text-red-600 hover:underline">Borrar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay empleados registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <div>{{ $employees->links() }}</div>

        {{-- Exportar con opciones: rango de fechas (por alta), área y formato. --}}
        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
            <button type="button" @click="open = ! open"
                    class="inline-flex items-center gap-1.5 rounded-md bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Exportar empleados
                <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
            </button>
            <form method="GET" action="{{ route('admin.employees.export') }}" x-show="open" x-cloak x-transition
                  class="absolute right-0 bottom-full z-20 mb-2 w-72 space-y-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-900/10">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Exportar empleados</p>
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
                    <label class="block text-xs text-slate-500">Área</label>
                    <select name="area" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Todas</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}">{{ $area->name }}</option>
                        @endforeach
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
