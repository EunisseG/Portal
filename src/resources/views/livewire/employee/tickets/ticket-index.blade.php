<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Mis tickets</h1>
            <p class="mt-1 text-slate-600">Solicitudes que has enviado a las áreas internas.</p>
        </div>
        <a href="{{ route('employee.tickets.create') }}" wire:navigate
           class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nuevo ticket</a>
    </div>

    {{-- Pestañas: activos / cerrados --}}
    <div class="mt-4 flex gap-1 border-b border-slate-200">
        <button type="button" wire:click="setTab('active')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'active' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Activos
        </button>
        <button type="button" wire:click="setTab('closed')"
                class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'closed' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Cerrados
        </button>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Número</th>
                    <th class="px-4 py-3">Asunto</th>
                    <th class="px-4 py-3">Área</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Última actividad</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($tickets as $ticket)
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('employee.tickets.show', $ticket) }}" wire:navigate
                               class="text-indigo-600 hover:underline">{{ $ticket->number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $ticket->internalArea?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticket->status->badgeClasses() }}">
                                {{ $ticket->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no has enviado tickets. Pulsa «Nuevo ticket» para crear uno.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
</div>
