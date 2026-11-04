<div class="mx-auto max-w-2xl">
    <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        Volver a mis tickets
    </a>

    <div class="mt-3">
        <h1 class="text-2xl font-semibold text-slate-900">Nuevo ticket</h1>
        <p class="mt-1 text-slate-600">Cuéntanos tu solicitud y te responderemos lo antes posible.</p>
    </div>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Asunto</label>
            <input type="text" wire:model="subject" placeholder="Resume tu solicitud en una frase"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('subject') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Departamento</label>
                <select wire:model="department" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($departmentOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('department') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Prioridad</label>
                <select wire:model="priority" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('priority') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Criticidad (impacto)</label>
                <select wire:model="criticality" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($criticalityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('criticality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Descripción</label>
            <textarea wire:model="body" rows="5" placeholder="Describe tu solicitud con detalle..."
                      class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Adjuntos <span class="font-normal text-slate-400">(opcional, máx. 5)</span></label>
            <input type="file" wire:model="attachments" multiple
                   class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
            <div wire:loading wire:target="attachments" class="mt-1 text-xs text-indigo-500">Subiendo...</div>
            @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
            <button type="submit" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                Crear ticket
            </button>
            <a href="{{ route('client.tickets.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
</div>
