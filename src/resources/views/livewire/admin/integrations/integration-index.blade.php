<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Integraciones instaladas</h1>
            <p class="mt-1 text-slate-600">Conecta WORTEV con sistemas externos. WORTEV siempre es la fuente de verdad.</p>
        </div>
        <a href="{{ route('admin.integrations.catalog') }}"
           class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Catálogo
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Integración</th>
                    <th class="px-4 py-3">Capacidad</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Última prueba</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($integrations as $integration)
                    <tr wire:key="int-{{ $integration->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900">{{ $integration->name }}</div>
                            @if ($integration->last_error)
                                <div class="text-xs text-red-500">{{ \Illuminate\Support\Str::limit($integration->last_error, 60) }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $integration->capability?->label() }}</td>
                        <td class="px-4 py-3">
                            @php
                                $badge = match ($integration->status) {
                                    'connected' => 'bg-green-50 text-green-700',
                                    'error' => 'bg-red-50 text-red-700',
                                    'disabled' => 'bg-slate-100 text-slate-500',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                                $statusLabel = match ($integration->status) {
                                    'connected' => 'Conectada',
                                    'error' => 'Con error',
                                    'disabled' => 'Desactivada',
                                    'pending' => 'Pendiente',
                                    default => 'Sin probar',
                                };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge }}">
                                {{ $integration->is_enabled ? $statusLabel : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $integration->last_tested_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @if ($integration->key === 'smtp')
                                    {{-- SMTP tiene su propia pantalla; "Editar" si ya está configurado. --}}
                                    <a href="{{ route('admin.settings.smtp') }}" class="text-slate-600 hover:text-slate-900 hover:underline">
                                        {{ $smtpConfigured ? 'Editar' : 'Configurar' }}
                                    </a>
                                @else
                                    <a href="{{ route('admin.integrations.configure', $integration) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Configurar</a>
                                @endif
                                <button wire:click="testConnection({{ $integration->id }})"
                                        wire:loading.attr="disabled" wire:target="testConnection({{ $integration->id }})"
                                        class="text-slate-600 hover:underline disabled:opacity-50">
                                    <span wire:loading.remove wire:target="testConnection({{ $integration->id }})">Probar</span>
                                    <span wire:loading wire:target="testConnection({{ $integration->id }})">Probando…</span>
                                </button>
                                <button wire:click="toggle({{ $integration->id }})" class="{{ $integration->is_enabled ? 'text-amber-600' : 'text-green-600' }} hover:underline">
                                    {{ $integration->is_enabled ? 'Desactivar' : 'Activar' }}
                                </button>
                                <button wire:click="uninstall({{ $integration->id }})"
                                        wire:confirm="¿Desinstalar {{ $integration->name }}? Se borrará su configuración y credenciales."
                                        class="text-red-600 hover:underline">Desinstalar</button>
                            </div>
                        </td>
                    </tr>
                    @if (isset($testResults[$integration->id]))
                        <tr wire:key="int-test-{{ $integration->id }}">
                            <td colspan="5" class="px-4 pb-3 pt-0">
                                <div class="rounded-md px-3 py-2 text-xs ring-1 {{ $testResults[$integration->id]['ok'] ? 'bg-green-50 text-green-700 ring-green-600/10' : 'bg-red-50 text-red-700 ring-red-600/10' }}">
                                    <span class="font-medium">{{ $testResults[$integration->id]['ok'] ? '✓' : '✗' }} {{ $integration->name }}:</span>
                                    {{ $testResults[$integration->id]['msg'] }}
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">
                            No hay integraciones instaladas. Ve al <a href="{{ route('admin.integrations.catalog') }}" class="text-slate-600 hover:text-slate-900 hover:underline">catálogo</a> para agregar una.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-sm">
        <a href="{{ route('admin.integrations.logs') }}" class="text-slate-600 hover:text-slate-900 hover:underline">Ver registros de integración →</a>
    </div>
</div>
