<div class="mx-auto max-w-4xl">
    <h1 class="text-2xl font-semibold text-slate-900">Encuestas de satisfacción</h1>
    <p class="mt-1 text-slate-600">Edita el título, las instrucciones y las preguntas de cada encuesta. Se generan al resolver o cerrar un ticket.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Pestañas: clientes / empleados --}}
    <div class="mt-4 border-b border-slate-200">
        <nav class="-mb-px flex gap-6 text-sm font-medium">
            <button type="button" wire:click="setTab('client')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'client' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Encuesta de clientes
            </button>
            <button type="button" wire:click="setTab('internal')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'internal' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Encuesta de empleados
            </button>
        </nav>
    </div>

    {{-- Cabecera: título e instrucciones --}}
    <form wire:submit="saveForm" class="mt-6 space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Presentación</h2>
        <div>
            <label class="block text-sm font-medium text-slate-700">Título</label>
            <input type="text" wire:model="title"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Instrucciones (opcional)</label>
            <textarea wire:model="instructions" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('instructions') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <button type="submit"
                wire:loading.attr="disabled" wire:target="saveForm"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
            <span wire:loading.remove wire:target="saveForm">Guardar presentación</span>
            <span wire:loading wire:target="saveForm">Guardando…</span>
        </button>
    </form>

    {{-- Preguntas --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">Preguntas</h2>
            @unless ($showQuestionForm)
                <button wire:click="newQuestion" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Agregar pregunta</button>
            @endunless
        </div>

        @if ($showQuestionForm)
            <div class="mt-4 space-y-3 rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Pregunta</label>
                    <input type="text" wire:model="q_label"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('q_label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Tipo</label>
                        <select wire:model="q_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 pt-7 text-sm text-slate-700">
                        <input type="checkbox" wire:model="q_required" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        Obligatoria
                    </label>
                    <label class="flex items-center gap-2 pt-7 text-sm text-slate-700">
                        <input type="checkbox" wire:model="q_active" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        Activa
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="saveQuestion"
                            wire:loading.attr="disabled" wire:target="saveQuestion"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveQuestion">{{ $editingQuestionId ? 'Guardar pregunta' : 'Agregar' }}</span>
                        <span wire:loading wire:target="saveQuestion">Guardando…</span>
                    </button>
                    <button wire:click="cancelQuestion" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                </div>
            </div>
        @endif

        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Orden</th>
                        <th class="px-4 py-2">Pregunta</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2">Obligatoria</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($questions as $question)
                        <tr wire:key="q-{{ $question->id }}">
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <button wire:click="move({{ $question->id }}, 'up')" class="text-slate-400 hover:text-slate-700" title="Subir">▲</button>
                                    <button wire:click="move({{ $question->id }}, 'down')" class="text-slate-400 hover:text-slate-700" title="Bajar">▼</button>
                                </div>
                            </td>
                            <td class="px-4 py-2 font-medium text-slate-800">{{ $question->label }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $question->type->label() }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $question->required ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-2">
                                @if ($question->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activa</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="editQuestion({{ $question->id }})"
                                            wire:loading.attr="disabled" wire:target="editQuestion({{ $question->id }})"
                                            class="text-indigo-600 hover:underline disabled:opacity-50">Editar</button>
                                    <button wire:click="deleteQuestion({{ $question->id }})"
                                            wire:confirm="¿Eliminar esta pregunta?"
                                            wire:loading.attr="disabled" wire:target="deleteQuestion({{ $question->id }})"
                                            class="text-red-600 hover:underline disabled:opacity-50">Borrar</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Esta encuesta aún no tiene preguntas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
