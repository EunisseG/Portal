<div class="mx-auto max-w-2xl">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Configurar: {{ $integration->name }}</h1>
        <a href="{{ route('admin.integrations.index') }}" class="text-sm text-slate-600 hover:underline">← Volver</a>
    </div>
    <p class="mt-1 text-slate-600">{{ $manifest?->description }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Estado</h2>
        @php
            $statusLabel = match ($integration->status) {
                'connected' => 'Conectada',
                'error' => 'Con error',
                'disabled' => 'Desactivada',
                'pending' => 'Pendiente',
                default => 'Sin probar',
            };
        @endphp
        <p class="mt-2 text-sm text-slate-600">
            Capacidad: <span class="font-medium">{{ $integration->capability?->label() }}</span> ·
            Estado: <span class="font-medium">{{ $integration->is_enabled ? $statusLabel : 'Inactiva' }}</span>
        </p>

        @if ($integration->key === 'smtp')
            <div class="mt-4">
                <a href="{{ route('admin.settings.smtp') }}"
                   class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Configurar servidor y credenciales de correo
                </a>
                <p class="mt-2 text-xs text-slate-400">Host, puerto, usuario, contraseña, remitente y correo de prueba.</p>
            </div>
        @endif

        @if ($integration->key === 'whm')
            <div class="mt-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-600/10">
                La conexión de WHM se configura <span class="font-medium">en cada servidor</span>, no aquí. Así puedes tener varios servidores WHM, cada uno con sus propias credenciales.
            </div>
            <div class="mt-3">
                <a href="{{ route('admin.servers.index') }}"
                   class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Ir a Servidores
                </a>
                <p class="mt-2 text-xs text-slate-400">En cada servidor: activa la conexión WHM y captura URL, usuario y token.</p>
            </div>
        @endif
    </div>

    {{-- Configuración y credenciales --}}
    @if ($manifest && (count($manifest->configFields) || count($manifest->secretFields)))
        <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Configuración</h2>

            @foreach ($manifest->configFields as $field)
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ \App\Integrations\Registry\IntegrationFieldCatalog::fieldLabel($field) }}</label>
                    @php $help = \App\Integrations\Registry\IntegrationFieldCatalog::fieldHelp($field); @endphp
                    @if ($help)
                        <p class="mt-0.5 text-xs text-slate-400">{{ $help }}</p>
                    @endif
                    <input type="text" wire:model="config.{{ $field }}"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            @endforeach

            @if (count($manifest->secretFields))
                <div class="border-t border-slate-100 pt-4">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Credenciales</h3>
                    <p class="mt-1 text-xs text-slate-400">Las credenciales se guardan cifradas y nunca se muestran completas. Deja el campo vacío para conservar la actual.</p>
                    @foreach ($manifest->secretFields as $field)
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-slate-700">{{ \App\Integrations\Registry\IntegrationFieldCatalog::fieldLabel($field) }}</label>
                            @php $help = \App\Integrations\Registry\IntegrationFieldCatalog::fieldHelp($field); @endphp
                            @if ($help)
                                <p class="mt-0.5 text-xs text-slate-400">{{ $help }}</p>
                            @endif
                            <input type="password" wire:model="secrets.{{ $field }}" autocomplete="new-password"
                                   placeholder="{{ $savedSecrets[$field] ?? 'Sin configurar' }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                    @endforeach
                </div>
            @endif

            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Guardar configuración
            </button>
        </form>
    @endif

    @if ($subscriptions->isNotEmpty())
        <form wire:submit="saveEvents" class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Eventos suscritos</h2>
            <p class="mt-1 text-sm text-slate-500">Elige a qué eventos de WORTEV reaccionará esta integración.</p>
            <div class="mt-3 space-y-2">
                @foreach ($subscriptions as $subscription)
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="enabledEvents" value="{{ $subscription->event_name }}"
                               class="mt-0.5 rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                        <span>
                            <span class="font-medium">{{ \App\Integrations\Registry\IntegrationFieldCatalog::eventLabel($subscription->event_name) }}</span>
                            @php $eventHelp = \App\Integrations\Registry\IntegrationFieldCatalog::eventHelp($subscription->event_name); @endphp
                            @if ($eventHelp)
                                <span class="block text-xs text-slate-400">{{ $eventHelp }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="mt-4 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Guardar eventos
            </button>
        </form>
    @endif
</div>
