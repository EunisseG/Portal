<div>
    <h1 class="text-2xl font-semibold text-slate-900">Correos enviados</h1>
    <p class="mt-1 text-slate-600">Inspecciona los mensajes individuales y gestiona los registros almacenados.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <x-admin.search-input class="mt-4" placeholder="Buscar por destinatario o asunto" debounce="400" />

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Destinatario</th>
                    <th class="px-4 py-3">Asunto</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr wire:key="email-{{ $log->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $log->sent_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $log->recipient }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($log->subject, 50) }}</td>
                        <td class="px-4 py-3">
                            @if ($log->success)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Enviado</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Error</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                <button wire:click="show({{ $log->id }})" class="text-indigo-600 hover:underline">Ver</button>
                                <button wire:click="delete({{ $log->id }})"
                                        wire:confirm="¿Eliminar este registro de correo?"
                                        class="text-red-600 hover:underline">Borrar</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No hay correos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>

    {{-- Detalle del mensaje --}}
    @if ($selected)
        <div class="fixed inset-0 z-30 flex items-center justify-center bg-slate-900/40 p-4" wire:click.self="closeDetail">
            <div class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                <div class="flex items-start justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $selected->subject }}</h2>
                    <button wire:click="closeDetail" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <dl class="mt-4 space-y-1 text-sm">
                    <div class="flex gap-2"><dt class="w-28 text-slate-500">Para</dt><dd class="text-slate-800">{{ $selected->recipient }}</dd></div>
                    <div class="flex gap-2"><dt class="w-28 text-slate-500">Fecha</dt><dd class="text-slate-800">{{ $selected->sent_at?->format('Y-m-d H:i:s') }}</dd></div>
                    <div class="flex gap-2"><dt class="w-28 text-slate-500">Estado</dt><dd class="text-slate-800">{{ $selected->success ? 'Enviado' : 'Error: '.$selected->error }}</dd></div>
                </dl>
                <div class="mt-4">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Contenido</div>
                    <pre class="mt-1 whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-sm text-slate-700">{{ $selected->body ?: '(sin cuerpo)' }}</pre>
                </div>
                @if ($selected->context)
                    <div class="mt-4">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Contexto</div>
                        <pre class="mt-1 whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-xs text-slate-600">{{ json_encode($selected->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
