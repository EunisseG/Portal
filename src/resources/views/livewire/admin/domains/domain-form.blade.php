<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar dominio' : 'Nuevo dominio' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Cliente</label>
            <select wire:model.live="client_id"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Selecciona un cliente</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->user->name }} — {{ $client->company ?: $client->user->email }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Nombre del dominio</label>
            <input type="text" wire:model="name" placeholder="ejemplo.com"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Servicio asociado (opcional)</label>
            <select wire:model="service_id"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Sin servicio</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                @endforeach
            </select>
            @if ($client_id === '')
                <p class="mt-1 text-xs text-slate-400">Selecciona primero un cliente para ver sus servicios.</p>
            @endif
            @error('service_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Estado</label>
                <select wire:model="status"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Registro</label>
                <input type="date" wire:model="registered_at"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('registered_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Vencimiento</label>
                <input type="date" wire:model="expires_at"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('expires_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="auto_renew"
                   class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
            Renovación automática
        </label>

        <div>
            <label class="block text-sm font-medium text-slate-700">Notas</label>
            <textarea wire:model="notes" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear dominio' }}
            </button>
            <a href="{{ route('admin.domains.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
