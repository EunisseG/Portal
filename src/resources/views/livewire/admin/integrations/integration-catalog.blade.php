<div>
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Catálogo de integraciones</h1>
            <p class="mt-1 text-slate-600">Proveedores disponibles para conectar.</p>
        </div>
        <a href="{{ route('admin.integrations.index') }}" class="text-sm text-slate-600 hover:underline">← Instaladas</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($manifests as $manifest)
            @php $installed = in_array($manifest->key, $installedKeys, true); @endphp
            <div wire:key="cat-{{ $manifest->key }}" class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-start justify-between">
                    <h2 class="font-semibold text-slate-900">{{ $manifest->name }}</h2>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium uppercase text-slate-500">
                        {{ $manifest->capability->label() }}
                    </span>
                </div>
                <p class="mt-2 flex-1 text-sm text-slate-500">{{ $manifest->description }}</p>
                @if ($manifest->supportsWebhooks)
                    <p class="mt-2 text-xs text-slate-400">Soporta webhooks</p>
                @endif
                <div class="mt-4">
                    @if ($installed)
                        <span class="rounded-md bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700">Instalada</span>
                    @else
                        <button wire:click="install('{{ $manifest->key }}')"
                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Instalar
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
