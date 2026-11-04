<div>
    @include('partials.client-page-header', [
        'title' => 'Servicios',
        'subtitle' => 'Tus servicios contratados y dominios registrados con WORTEV.',
    ])

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    {{-- Pestañas: Mis Servicios / Mis Dominios --}}
    <div class="mt-6 border-b border-slate-200">
        <nav class="-mb-px flex gap-6" aria-label="Pestañas">
            @if ($canServices)
                <button type="button" wire:click="setTab('services')"
                        class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition {{ $tab === 'services' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                    Mis Servicios
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $services->count() }}</span>
                </button>
            @endif
            @if ($canDomains)
                <button type="button" wire:click="setTab('domains')"
                        class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition {{ $tab === 'domains' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                    Mis Dominios
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $domains->count() }}</span>
                </button>
            @endif
        </nav>
    </div>

    <div class="mt-6">
        @if ($tab === 'domains' && $canDomains)
            @include('livewire.client.services.partials.domains-table', ['domains' => $domains])
        @elseif ($canServices)
            @include('livewire.client.services.partials.services-grid', ['services' => $services])
        @else
            @include('livewire.client.services.partials.domains-table', ['domains' => $domains])
        @endif
    </div>

    {{-- Modal: renovar dominio (elegir años) --}}
    @if ($renewingDomain)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Renovar dominio</h2>
                    <button type="button" wire:click="cancelRenew" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="renewDomain" class="space-y-4 px-5 py-4">
                    <p class="text-sm text-slate-600">
                        <span class="font-medium text-slate-900">{{ $renewingDomain->name }}</span><br>
                        Vence actualmente: {{ $renewingDomain->expires_at?->translatedFormat('d M Y') ?: '—' }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Años a renovar</label>
                        <select wire:model="renewYears" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @for ($y = 1; $y <= 5; $y++)
                                <option value="{{ $y }}">{{ $y }} {{ $y === 1 ? 'año' : 'años' }}</option>
                            @endfor
                        </select>
                        @error('renewYears') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-400">Enviaremos tu solicitud de renovación; nuestro equipo la confirmará.</p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="cancelRenew"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Solicitar renovación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
