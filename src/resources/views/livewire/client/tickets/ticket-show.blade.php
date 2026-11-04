<div class="mx-auto max-w-3xl">
    <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        Volver a mis tickets
    </a>

    {{-- Cabecera del ticket --}}
    <div class="mt-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600">{{ $ticket->number }}</span>
                    <span>·</span>
                    <span>{{ $ticket->department?->label() }}</span>
                    @if ($ticket->priority)
                        <span>·</span>
                        <span class="rounded-full px-2 py-0.5 font-medium {{ $ticket->priority?->badgeClasses() }}">{{ $ticket->priority?->label() }}</span>
                    @endif
                </div>
            </div>
            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $ticket->status?->badgeClasses() }}">
                {{ $ticket->status?->label() }}
            </span>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6">
        @include('partials.ticket-thread', ['ticket' => $ticket, 'attachmentRoute' => 'tickets.attachment'])
    </div>

    @if ($ticket->status !== \App\Domain\Tickets\Enums\TicketStatus::Closed)
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-medium text-slate-900">Responder</h2>
            <form wire:submit="reply" class="mt-4 space-y-4">
                <div>
                    <textarea wire:model="body" rows="4" placeholder="Escribe tu respuesta..."
                              class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Adjuntos <span class="font-normal text-slate-400">(opcional, máx. 5)</span></label>
                    <input type="file" wire:model="attachments" multiple
                           class="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                    <div wire:loading wire:target="attachments" class="mt-1 text-xs text-indigo-500">Subiendo...</div>
                    @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                    Enviar respuesta
                </button>
            </form>
        </div>
    @else
        <div class="mt-6 flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            Este ticket está cerrado.
        </div>

        @if ($ticket->survey)
            <div class="mt-6">
                @include('partials.survey-form', ['survey' => $ticket->survey, 'surveyForm' => $surveyForm])
            </div>
        @endif
    @endif
</div>
