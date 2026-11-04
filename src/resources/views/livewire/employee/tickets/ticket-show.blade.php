<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $ticket->number }} · Área: {{ $ticket->internalArea?->name ?? '—' }} ·
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
            </p>
        </div>
        <a href="{{ route('employee.dashboard') }}" wire:navigate class="text-sm text-slate-600 hover:underline">Volver</a>
    </div>

    {{-- Hilo de mensajes --}}
    <div class="mt-6 space-y-4">
        @foreach ($ticket->messages as $message)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-800">
                        {{ $message->is_staff ? 'Equipo de soporte' : ($message->author?->name ?? 'Tú') }}
                    </span>
                    <span class="text-xs text-slate-400">{{ $message->created_at?->diffForHumans() }}</span>
                </div>
                <div class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $message->body }}</div>

                @if ($message->attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($message->attachments as $attachment)
                            <a href="{{ route('tickets.attachment', $attachment) }}"
                               class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600 hover:bg-slate-200">
                                {{ $attachment->original_name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Encuesta de satisfacción (cuando el ticket se resolvió/cerró) --}}
    @if ($ticket->survey)
        <div class="mt-6">
            @include('partials.survey-form', ['survey' => $ticket->survey, 'surveyForm' => $surveyForm])
        </div>
    @endif

    {{-- Responder --}}
    @if ($ticket->status !== \App\Domain\Tickets\Enums\TicketStatus::Closed)
        <form wire:submit="reply" class="mt-6 space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">Responder</h2>
            <div>
                <textarea wire:model="body" rows="4"
                          class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <input type="file" wire:model="attachments" multiple class="block w-full text-sm text-slate-600">
                @error('attachments.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="reply,attachments"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">Enviar respuesta</button>
        </form>
    @else
        <p class="mt-6 rounded-md bg-slate-100 px-4 py-3 text-sm text-slate-500">Este ticket está cerrado.</p>
    @endif
</div>
