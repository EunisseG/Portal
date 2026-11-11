<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">Nuevo ticket interno</h1>
    <p class="mt-1 text-slate-600">Describe tu solicitud y elige el área a la que va dirigida.</p>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Asunto</label>
            <input type="text" wire:model="subject"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Área destino</label>
                <select wire:model="internal_area_id"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">— Elige área —</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}">{{ $area->name }}</option>
                    @endforeach
                </select>
                @error('internal_area_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Prioridad</label>
                <select wire:model="priority"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Criticidad</label>
                <select wire:model="criticality"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($criticalityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('criticality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <p class="block text-sm font-medium text-slate-700">Descripción</p>
            <x-rich-editor model="body" class="mt-1" label="Descripción" placeholder="Describe tu solicitud con detalle..." />
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Adjuntos (opcional)</label>
            <input type="file" wire:model="attachments" multiple
                   class="mt-1 block w-full text-sm text-slate-600">
            @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="save,attachments"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">Enviar ticket</button>
            <a href="{{ route('employee.dashboard') }}" wire:navigate
               class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
