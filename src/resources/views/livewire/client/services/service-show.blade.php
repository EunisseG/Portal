<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $service->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $service->type?->label() }}</p>
        </div>
        <a href="{{ route('client.services.index') }}" class="text-sm text-slate-600 hover:underline">← Mis servicios</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Datos del servicio --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-slate-500">Estado</dt>
                <dd>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">
                        {{ $service->status?->label() }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Ciclo</dt>
                <dd class="text-slate-800">{{ $service->billing_cycle?->label() ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Precio</dt>
                <dd class="font-medium text-slate-800">{{ $service->total()->format() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Vence</dt>
                <dd class="text-slate-800">{{ $service->expires_at?->translatedFormat('d M Y') ?: '—' }}</dd>
            </div>
        </dl>

        @if ($service->domains->isNotEmpty())
            <div class="mt-4 border-t border-slate-100 pt-4">
                <dt class="text-xs text-slate-500">Dominios</dt>
                <dd class="mt-1 text-slate-800">{{ $service->domains->pluck('name')->implode(', ') }}</dd>
            </div>
        @endif
    </div>

    {{-- Correos (solo hosting) --}}
    @if ($this->isHosting())
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-slate-900">Correos</h2>
                    <p class="text-xs text-slate-400">Cuentas de correo de tu hosting y su uso de espacio.</p>
                </div>
                @if ($email['ok'])
                    <button type="button" wire:click="refreshMailboxes"
                            class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Actualizar
                    </button>
                @endif
            </div>

            @if (! $email['ok'])
                <div class="mt-4 rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    {{ $email['message'] ?: 'No hay información de correos disponible por ahora.' }}
                </div>
            @elseif (empty($email['mailboxes']))
                <div class="mt-4 rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    Este hosting no tiene cuentas de correo creadas.
                </div>
            @else
                <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Buzón</th>
                                <th class="px-4 py-2">Uso</th>
                                <th class="px-4 py-2">Cuota</th>
                                <th class="px-4 py-2 w-40">Ocupación</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($email['mailboxes'] as $box)
                                <tr wire:key="mbox-{{ $loop->index }}" class="transition hover:bg-slate-50/70">
                                    <td class="px-4 py-2 font-medium text-slate-900">{{ $box['email'] }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ number_format($box['disk_used_mb'], 1) }} MB</td>
                                    <td class="px-4 py-2 text-slate-600">
                                        {{ $box['disk_quota_mb'] !== null ? number_format($box['disk_quota_mb'], 0).' MB' : 'Ilimitada' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($box['percent'] !== null)
                                            <div class="flex items-center gap-2">
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-full rounded-full {{ $box['percent'] >= 90 ? 'bg-red-500' : ($box['percent'] >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                                         style="width: {{ $box['percent'] }}%"></div>
                                                </div>
                                                <span class="w-9 text-right text-xs text-slate-500">{{ $box['percent'] }}%</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
