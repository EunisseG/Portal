<div class="md:flex md:gap-6">
    @include('partials.account-sidebar', ['active' => 'emails'])

    <div class="mt-6 min-w-0 flex-1 md:mt-0">
        <h1 class="text-2xl font-semibold text-slate-900">Emails Recibidos</h1>

        <div class="mt-3 flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-slate-500 shadow-sm ring-1 ring-slate-900/5">
            <span>Área del Cliente</span>
            <span class="text-slate-300">›</span>
            <span class="font-medium text-indigo-600">Emails Recibidos</span>
        </div>

        <p class="mt-4 text-sm text-slate-500">Correos que el portal te ha enviado a tus direcciones registradas.</p>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Asunto</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}" class="transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ optional($log->sent_at)->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $log->subject }}</td>
                            <td class="px-4 py-3">
                                @if ($log->success)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Entregado</span>
                                @else
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Fallido</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="view({{ $log->id }})" class="text-indigo-600 hover:underline">Ver</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">Aún no has recibido correos del portal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>

    {{-- Detalle del correo --}}
    @if ($detail)
        <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 py-10">
            <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl ring-1 ring-slate-900/5">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $detail->subject }}</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Para {{ $detail->recipient }} · {{ optional($detail->sent_at)->format('d/m/Y H:i') ?? 'sin fecha' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="px-6 py-5">
                    @unless ($detail->success)
                        <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
                            Este correo no pudo entregarse.@if ($detail->error) {{ $detail->error }}@endif
                        </div>
                    @endunless
                    <div class="prose prose-sm max-w-none text-slate-700">
                        {!! $detail->body !!}
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                    <button type="button" wire:click="closeDetail" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
