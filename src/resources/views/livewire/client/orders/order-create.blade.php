<div class="mx-auto max-w-3xl">
    @include('partials.client-page-header', [
        'title' => 'Hacer un pedido',
        'subtitle' => 'Elige un producto. Se generará tu orden de pago para que puedas pagarla.',
    ])

    <div class="mt-4">
        <a href="{{ route('client.services.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Volver a servicios
        </a>
    </div>

    <form wire:submit="place" class="mt-4 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div>
            <label class="block text-sm font-medium text-slate-700">Producto</label>
            <select wire:model.live="productId"
                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Selecciona un producto</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name }}
                        @if ($product->category) — {{ $product->category->name }} @endif
                    </option>
                @endforeach
            </select>
            @error('productId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($products->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Por ahora no hay productos disponibles.</p>
            @endif
        </div>

        @if ($selected)
            @if ($selected->description)
                <p class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $selected->description }}</p>
            @endif

            @if ($selected->isRecurring() && $selected->prices->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-slate-700">Ciclo de facturación</label>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        @foreach ($selected->prices as $price)
                            <label wire:key="cycle-{{ $price->id }}"
                                   class="flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 text-sm transition {{ $cycle === $price->billing_cycle->value ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300' }}">
                                <span class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="cycle" value="{{ $price->billing_cycle->value }}"
                                           class="text-indigo-600 focus:ring-indigo-500">
                                    <span class="font-medium text-slate-800">{{ $price->billing_cycle->label() }}</span>
                                </span>
                                <span class="text-slate-700">{{ $price->total()->format() }}
                                    <span class="text-xs text-slate-400">IVA incl.</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('cycle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <p class="text-sm text-slate-700">
                    Precio: <span class="font-semibold">{{ $selected->price()->format() }}</span>
                    <span class="text-xs text-slate-400">pago único</span>
                </p>
            @endif

            {{-- Dominio: solo para hosting --}}
            @if ($usesDomain)
                <div class="rounded-lg border border-slate-200 p-4">
                    <h2 class="text-sm font-semibold text-slate-900">Dominio</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Tu hosting va ligado a un dominio. ¿Ya tienes uno?</p>

                    <div class="mt-3 flex flex-wrap gap-4">
                        @if ($availableDomains->isNotEmpty())
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="radio" wire:model.live="domainMode" value="existing"
                                       class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Ya tengo un dominio en el portal
                            </label>
                        @endif
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="radio" wire:model.live="domainMode" value="new"
                                   class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Dominio nuevo
                        </label>
                    </div>

                    @if ($domainMode === 'existing' && $availableDomains->isNotEmpty())
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-slate-700">Tu dominio</label>
                            <select wire:model="domainId"
                                    class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Selecciona un dominio</option>
                                @foreach ($availableDomains as $d)
                                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                                @endforeach
                            </select>
                            @error('domainId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div class="mt-3 space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Nombre del dominio</label>
                                <input type="text" wire:model.live.debounce.400ms="domainName" placeholder="miempresa.com"
                                       class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('domainName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <span class="block text-sm font-medium text-slate-700">¿De dónde es el dominio?</span>
                                <div class="mt-1 flex flex-wrap gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="radio" wire:model.live="domainOrigin" value="owned"
                                               class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        Ya lo tengo en otro registrador
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="radio" wire:model.live="domainOrigin" value="purchase"
                                               class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        Quiero comprarlo con ustedes
                                    </label>
                                </div>
                            </div>

                            @if ($domainOrigin === 'purchase')
                                @if ($domainNeedsQuote)
                                    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        Esa extensión se cotiza aparte. Tu pedido se genera ahora sin costo de dominio
                                        y nuestro equipo te confirmará el precio.
                                    </p>
                                @else
                                    <p class="rounded-md bg-green-50 px-3 py-2 text-xs text-green-800">
                                        Los dominios .com y .com.mx los registramos sin costo con tu hosting.
                                    </p>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        @endif

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit" wire:loading.attr="disabled" wire:target="place"
                    @disabled(! $selected)
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="place">Generar orden de pago</span>
                <span wire:loading wire:target="place">Generando...</span>
            </button>
        </div>
    </form>
</div>
