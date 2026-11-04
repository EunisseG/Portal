<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo ticket</h1>
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-slate-600 hover:underline">← Volver</a>
    </div>
    <p class="mt-1 text-slate-600">Abre un ticket para uno o varios clientes. Cada cliente verá solo el suyo.</p>

    <form wire:submit="save" class="mt-6 space-y-6">
        {{-- Destinatarios --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Clientes destinatarios</h2>

            @if (count($clientIds))
                <p class="mt-1 text-sm text-slate-600">{{ count($clientIds) }} cliente(s) seleccionado(s)</p>
            @endif
            @error('clientIds') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            <input type="search" wire:model.live.debounce.300ms="clientSearch" placeholder="Buscar por empresa, nombre o correo..."
                   class="mt-3 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">

            <div class="mt-3 max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-md ring-1 ring-slate-200">
                @forelse ($clients as $client)
                    <label wire:key="cli-{{ $client->id }}" class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50">
                        <input type="checkbox"
                               wire:click="toggleClient({{ $client->id }})"
                               @checked(in_array($client->id, $clientIds, true))
                               class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        <span class="text-sm">
                            <span class="font-medium text-slate-800">{{ $client->user->name }}</span>
                            <span class="text-slate-500">· {{ $client->user->email }}</span>
                            @if ($client->company) <span class="text-slate-400">· {{ $client->company }}</span> @endif
                        </span>
                    </label>
                @empty
                    <div class="px-3 py-4 text-center text-sm text-slate-500">No hay clientes que coincidan.</div>
                @endforelse
            </div>
        </div>

        {{-- Contenido del ticket --}}
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="space-y-4">
                @if ($presetServices->isNotEmpty())
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Servicio relacionado (opcional)</label>
                        <select wire:model.live="serviceId"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">— Ninguno —</option>
                            @foreach ($presetServices as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Al elegir un servicio se autollena el asunto y el mensaje.</p>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-slate-700">Asunto</label>
                    <input type="text" wire:model="subject"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Departamento</label>
                        <select wire:model="department" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                            @foreach ($departmentOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Prioridad</label>
                        <select wire:model="priority" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                            @foreach ($priorityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Criticidad (impacto)</label>
                        <select wire:model="criticality" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                            @foreach ($criticalityOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('criticality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($cannedResponses->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-slate-600">Respuesta preparada:</label>
                        <select wire:change="applyCanned($event.target.value)"
                                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <option value="">— Seleccionar —</option>
                            @foreach ($cannedResponses as $canned)
                                <option value="{{ $canned->id }}">{{ $canned->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-slate-700">Mensaje</label>
                    <textarea wire:model="body" rows="6"
                              class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <button type="submit"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Crear ticket(s)
        </button>
    </form>
</div>
