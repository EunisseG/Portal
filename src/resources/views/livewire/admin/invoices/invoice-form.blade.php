<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar factura' : 'Subir factura' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Cliente</label>
            <select wire:model="client_id"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Selecciona un cliente</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->user->name }} — {{ $client->company ?: $client->user->email }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Archivos --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Archivo PDF @unless($this->isEditing())<span class="text-red-500">*</span>@endunless</label>
                <input type="file" wire:model="pdf" accept="application/pdf"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-slate-800">
                <div wire:loading wire:target="pdf" class="mt-1 text-xs text-slate-400">Procesando PDF...</div>
                @if ($this->isEditing())
                    <p class="mt-1 text-xs text-slate-400">Deja vacío para conservar el PDF actual.</p>
                @endif
                @error('pdf') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Archivo XML (CFDI, opcional)</label>
                <input type="file" wire:model="xml" accept="text/xml,application/xml,.xml"
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <div wire:loading wire:target="xml" class="mt-1 text-xs text-slate-400">Leyendo XML...</div>
                @error('xml') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($extractionNote)
            <div class="rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-700 ring-1 ring-blue-600/10">
                {{ $extractionNote }}
            </div>
        @endif

        {{-- Datos (autocompletables) --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Folio</label>
                <input type="text" wire:model="folio"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('folio') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">UUID / Folio fiscal</label>
                <input type="text" wire:model="uuid"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('uuid') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Total (MXN)</label>
                <input type="number" step="0.01" min="0" wire:model="total"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('total') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
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
                <label class="block text-sm font-medium text-slate-700">Emisión</label>
                <input type="date" wire:model="issued_at"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('issued_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Fecha de vencimiento</label>
            <input type="date" wire:model="due_at"
                   class="mt-1 block w-full max-w-xs rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('due_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Notas</label>
            <textarea wire:model="notes" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Subir factura' }}
            </button>
            <a href="{{ route('admin.invoices.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
