<div>
    @include('partials.client-page-header', [
        'title' => 'Mis tickets',
        'subtitle' => 'Solicitudes de soporte que has abierto con WORTEV.',
        'action' => '<a href="'.route('client.tickets.create').'" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">'
            .'<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>'
            .'Nuevo ticket</a>',
    ])

    <div class="mt-5 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Número</th>
                        <th class="px-5 py-3">Asunto</th>
                        <th class="px-5 py-3">Prioridad</th>
                        <th class="px-5 py-3">Estado</th>
                        <th class="px-5 py-3">Última respuesta</th>
                        <th class="px-5 py-3 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($tickets as $ticket)
                        <tr wire:key="ct-{{ $ticket->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5 font-medium text-slate-900">{{ $ticket->number }}</td>
                            <td class="px-5 py-3.5 text-slate-700">{{ $ticket->subject }}</td>
                            <td class="px-5 py-3.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticket->priority?->badgeClasses() }}">
                                    {{ $ticket->priority?->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticket->status?->badgeClasses() }}">
                                    {{ $ticket->status?->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500">{{ $ticket->last_reply_at?->diffForHumans() ?: '—' }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('client.tickets.show', $ticket) }}"
                                   class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline">
                                    Abrir
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12">
                                <div class="flex flex-col items-center text-center">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                                    <p class="mt-3 text-sm text-slate-500">Aún no has abierto tickets.</p>
                                    <a href="{{ route('client.tickets.create') }}" class="mt-3 text-sm font-medium text-indigo-600 hover:underline">Abrir tu primer ticket</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
</div>
