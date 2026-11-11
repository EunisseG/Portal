<div class="mx-auto max-w-4xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $ticket->number }} · {{ $ticket->client->user->name }} · {{ $ticket->department?->label() }}
            </p>
        </div>
        <a href="{{ route('admin.tickets.index') }}" class="text-sm text-slate-600 hover:underline">← Volver</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    {{-- Controles de gestión (staff) --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Estado</label>
            <div class="mt-2 flex gap-2">
                <select wire:model="status" class="block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="updateStatus"
                        class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Aplicar</button>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Prioridad</label>
            <div class="mt-2 flex gap-2">
                <select wire:model="priority" class="block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @foreach ($priorityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="updatePriority"
                        class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Aplicar</button>
            </div>
        </div>

        {{-- Criticidad (impacto) + objetivo de SLA --}}
        <div>
            <label class="block text-xs font-medium uppercase tracking-wide text-slate-400">Criticidad</label>
            <div class="mt-2 flex gap-2">
                <select wire:model="criticality" class="block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @foreach ($criticalityOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" wire:click="updateCriticality"
                        class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">Aplicar</button>
            </div>
            @if ($slaTarget['resolution_minutes'] !== null)
                <p class="mt-1 text-xs text-slate-400">
                    SLA: respuesta en {{ $slaTarget['first_response_minutes'] }} min · resolución en {{ $slaTarget['resolution_minutes'] }} min
                    @if ($slaTarget['source'] === 'client')
                        <span class="ml-1 rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">SLA del cliente</span>
                    @endif
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-500">1ª respuesta:</span>
                    <span class="rounded-full px-2 py-0.5 font-medium {{ $slaFrtBadge['classes'] }}">{{ $slaFrtBadge['label'] }}</span>
                    <span class="text-slate-500">Resolución:</span>
                    <span class="rounded-full px-2 py-0.5 font-medium {{ $slaResBadge['classes'] }}">{{ $slaResBadge['label'] }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Hilo --}}
    <div class="mt-6">
        @include('partials.ticket-thread', ['ticket' => $ticket, 'attachmentRoute' => 'tickets.attachment'])
    </div>

    {{-- Responder --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-lg font-medium text-slate-900">Responder</h2>
        <form wire:submit="reply" class="mt-4 space-y-4">
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
                <x-rich-editor model="body" label="Respuesta" placeholder="Escribe tu respuesta..." />
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Adjuntos (opcional, máx. 5)</label>
                <input type="file" wire:model="attachments" multiple
                       class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                <div wire:loading wire:target="attachments" class="mt-1 text-xs text-slate-400">Subiendo...</div>
                @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                Enviar respuesta
            </button>
        </form>
    </div>
</div>
