{{--
    Detalle de ticket (cliente y empleado), estilo "centro de soporte".
    Espera:
      $ticket       Ticket con messages.author, messages.attachments y survey cargados.
      $surveyForm   Formulario de encuesta del tipo del ticket.
      $meta         array<int, array{label: string, value: ?string, badge?: ?string}> datos de la fila.
      $backRoute    URL de regreso.
      $backLabel    Texto del enlace de regreso.
      $attachments  Archivos temporales seleccionados (propiedad Livewire).
--}}
@php
    $isClosed = $ticket->status === \App\Domain\Tickets\Enums\TicketStatus::Closed;
    $history = $ticket->messages->sortByDesc('id');
    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    };
@endphp

<div class="mx-auto max-w-4xl">
    <a href="{{ $backRoute }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        {{ $backLabel }}
    </a>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10" role="status">
            {{ session('status') }}
        </div>
    @endif

    {{-- Cabecera: número + asunto, estado y cerrar --}}
    <div class="mt-3 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-start justify-between gap-4 p-6">
            <h1 class="min-w-0 text-xl font-semibold text-slate-900">
                <span class="text-slate-500">#{{ $ticket->number }}</span>
                {{ $ticket->subject }}
            </h1>
            <div class="flex shrink-0 items-center gap-4">
                <div class="text-right">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Estado</p>
                    <span class="mt-1 inline-flex rounded-full px-3 py-0.5 text-xs font-medium {{ $ticket->status?->badgeClasses() }}">
                        {{ $ticket->status?->label() }}
                    </span>
                </div>
                @if (! $isClosed)
                    @can('close', $ticket)
                        <button type="button" wire:click="close"
                                wire:confirm="¿Seguro que deseas cerrar este ticket? Ya no podrás responder en él."
                                wire:loading.attr="disabled" wire:target="close"
                                class="rounded-lg border border-red-500 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50">
                            Cerrar ticket
                        </button>
                    @endcan
                @endif
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-slate-100 px-6 py-4 sm:grid-cols-4">
            @foreach ($meta as $item)
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item['label'] }}</dt>
                    <dd class="mt-1 text-sm text-slate-900">
                        @if (! empty($item['badge']) && filled($item['value']))
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item['badge'] }}">{{ $item['value'] }}</span>
                        @else
                            {{ filled($item['value']) ? $item['value'] : '—' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- Responder --}}
    @if (! $isClosed)
        <form wire:submit="reply" class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <p class="block text-sm font-semibold text-slate-900">Escribe tu respuesta</p>
            <x-rich-editor model="body" class="mt-2" label="Escribe tu respuesta"
                           placeholder="Describe tu respuesta o agrega más detalles..." />
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            {{-- Zona de adjuntos: el input cubre toda el área, así que acepta clic y arrastrar/soltar --}}
            <div class="relative mt-4 rounded-lg border-2 border-dashed border-slate-300 px-4 py-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40">
                <input id="ticket-reply-files" type="file" wire:model="attachments" multiple
                       class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                       aria-label="Adjuntar archivos (máximo 5, 10 MB cada uno)">
                <svg class="mx-auto h-6 w-6 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/></svg>
                <p class="mt-1 text-sm text-slate-600">
                    <span class="font-medium text-emerald-700">Agrega un adjunto</span> o suelta los archivos aquí
                </p>
                <p class="text-xs text-slate-400">Máximo 5 archivos, 10 MB cada uno</p>
                <p wire:loading wire:target="attachments" class="mt-1 text-xs text-emerald-600">Subiendo...</p>
            </div>
            @error('attachments') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            @if (! empty($attachments))
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($attachments as $file)
                        <li class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700">📎 {{ $file->getClientOriginalName() }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="reply,attachments"
                        class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50">
                    Responder
                </button>
            </div>
        </form>
    @else
        <div class="mt-6 flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
            Este ticket está cerrado.
        </div>
    @endif

    {{-- Encuesta de satisfacción (se genera al resolver o cerrar) --}}
    @if ($ticket->survey)
        <div class="mt-6">
            @include('partials.survey-form', ['survey' => $ticket->survey, 'surveyForm' => $surveyForm])
        </div>
    @endif

    {{-- Historial de conversación (más reciente primero) --}}
    <section class="mt-6 rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5" aria-labelledby="ticket-history-title">
        <h2 id="ticket-history-title" class="border-b border-slate-100 px-6 py-4 text-base font-semibold text-slate-900">
            Historial de conversación ({{ $history->count() }})
        </h2>

        <ul class="divide-y divide-slate-100">
            @foreach ($history as $message)
                @php
                    $authorName = $message->is_staff
                        ? ($staffName ?? $message->author?->name ?? 'Equipo WORTEV')
                        : ($message->author?->name ?? 'Sistema');
                @endphp
                <li class="flex gap-4 px-6 py-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold {{ $message->is_staff ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' }}" aria-hidden="true">
                        {{ $initials($authorName) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <p class="text-sm font-semibold text-slate-900">
                                {{ $authorName }}
                                <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $message->is_staff ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $message->is_staff ? 'WORTEV' : ($ticket->isInternal() ? 'Empleado' : 'Cliente') }}
                                </span>
                            </p>
                            <time datetime="{{ $message->created_at?->toIso8601String() }}" class="text-xs text-slate-500">
                                {{ $message->created_at?->format('d/m/Y H:i') }}
                            </time>
                        </div>
                        @if (! $message->is_staff && $message->author?->email)
                            <p class="text-xs text-slate-500">{{ $message->author->email }}</p>
                        @endif

                        <div class="rich-text mt-3 text-sm text-slate-700">{!! $message->bodyHtml() !!}</div>

                        @if ($message->attachments->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($message->attachments as $attachment)
                                    <a href="{{ route('tickets.attachment', $attachment) }}"
                                       class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700 hover:bg-slate-200">
                                        📎 {{ $attachment->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
