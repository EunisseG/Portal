<div class="mx-auto max-w-5xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $ticket->number }} · Interno · Empleado: {{ $ticket->openedBy?->name ?? '—' }} · Área: {{ $ticket->internalArea?->name ?? '—' }}
            </p>
        </div>
        <a href="{{ route('admin.internal-tickets.index') }}" wire:navigate class="text-sm text-slate-600 hover:underline">Volver</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Hilo + responder --}}
        <div class="lg:col-span-2 space-y-4">
            @foreach ($ticket->messages as $message)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium {{ $message->is_staff ? 'text-indigo-700' : 'text-slate-800' }}">
                            {{ $message->is_staff ? 'Soporte: '.($message->author?->name ?? '') : ($message->author?->name ?? 'Empleado') }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $message->created_at?->diffForHumans() }}</span>
                    </div>
                    <div class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</div>
                    @if ($message->attachments->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($message->attachments as $attachment)
                                <a href="{{ route('tickets.attachment', $attachment) }}" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600 hover:bg-slate-200">{{ $attachment->original_name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @can('reply', $ticket)
                <form wire:submit="reply" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-semibold text-slate-900">Responder</h2>
                    <textarea wire:model="body" rows="4" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <input type="file" wire:model="attachments" multiple class="block w-full text-sm text-slate-600">
                    @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Enviar respuesta</button>
                </form>
            @endcan
        </div>

        {{-- Gestión --}}
        <div class="space-y-4">
            @can('manage', $ticket)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-semibold text-slate-900">Gestión</h2>

                    <div class="mt-3">
                        <label class="block text-xs font-medium text-slate-500">Estado</label>
                        <div class="mt-1 flex gap-2">
                            <select wire:model="status" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button wire:click="updateStatus" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">OK</button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="block text-xs font-medium text-slate-500">Prioridad</label>
                        <div class="mt-1 flex gap-2">
                            <select wire:model="priority" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($priorityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button wire:click="updatePriority" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">OK</button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="block text-xs font-medium text-slate-500">Criticidad</label>
                        <div class="mt-1 flex gap-2">
                            <select wire:model="criticality" class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($criticalityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button wire:click="updateCriticality" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">OK</button>
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</div>
