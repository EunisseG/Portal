{{-- Hilo de mensajes de un ticket. Espera: $ticket (con messages.author, messages.attachments), $attachmentRoute (nombre de ruta de descarga) --}}
<div class="space-y-4">
    @foreach ($ticket->messages as $message)
        <div class="rounded-lg p-4 ring-1 {{ $message->is_staff ? 'bg-indigo-50 ring-indigo-100' : 'bg-white ring-slate-900/5' }}">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-800">
                    {{ $message->author?->name ?? 'Sistema' }}
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs {{ $message->is_staff ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $message->is_staff ? 'WORTEV' : 'Cliente' }}
                    </span>
                </span>
                <span class="text-xs text-slate-400">{{ $message->created_at?->format('Y-m-d H:i') }}</span>
            </div>
            <div class="rich-text mt-2 text-sm text-slate-700">{!! $message->bodyHtml() !!}</div>

            @if ($message->attachments->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($message->attachments as $attachment)
                        <a href="{{ route($attachmentRoute, $attachment) }}"
                           class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700 hover:bg-slate-200">
                            📎 {{ $attachment->original_name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
