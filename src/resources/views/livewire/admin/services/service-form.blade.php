<div class="mx-auto max-w-2xl">
    <a href="{{ route('admin.services.index') }}"
       class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        Volver a pedidos
    </a>

    <h1 class="mt-3 text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar pedido' : 'Nuevo pedido' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div x-data="{ open: false }" @click.outside="open = false">
            <label class="block text-sm font-medium text-slate-700">Cliente</label>

            @if ($selectedClientLabel !== '')
                {{-- Cliente seleccionado: se muestra con opción de cambiarlo. --}}
                <div class="mt-1 flex items-center justify-between rounded-md border border-slate-300 px-3 py-2">
                    <span class="text-sm text-slate-800">{{ $selectedClientLabel }}</span>
                    <button type="button" wire:click="clearClient" class="text-xs font-medium text-indigo-600 hover:underline">Cambiar</button>
                </div>
            @else
                {{-- Buscador con resultados en el mismo campo. --}}
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    </span>
                    <input type="search" wire:model.live.debounce.300ms="clientSearch"
                           @focus="open = true" x-on:input="open = true"
                           placeholder="Buscar y seleccionar cliente por nombre, empresa o correo..."
                           class="block w-full rounded-md border-slate-300 pl-9 shadow-sm focus:border-slate-500 focus:ring-slate-500">

                    @if ($clientResults->isNotEmpty())
                        <ul x-show="open" x-transition
                            class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                            @foreach ($clientResults as $client)
                                <li>
                                    <button type="button" wire:click="selectClient({{ $client->id }})" @click="open = false"
                                            class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-slate-50">
                                        <span class="font-medium text-slate-800">{{ $client->user->name }}</span>
                                        <span class="text-xs text-slate-500">{{ $client->company ?: $client->user->email }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (trim($clientSearch) !== '')
                        <div x-show="open" class="absolute z-20 mt-1 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm text-slate-400 shadow-lg">
                            Sin coincidencias.
                        </div>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-400">Escribe para buscar y selecciona un cliente de la lista.</p>
            @endif
            @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Categoría → producto del catálogo (planes de hosting incluidos). --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="order-category" class="block text-sm font-medium text-slate-700">Categoría</label>
                <select id="order-category" wire:model.live="category_id"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">— Selecciona una categoría —</option>
                    @foreach ($categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="order-product" class="block text-sm font-medium text-slate-700">Nombre del servicio</label>
                <select id="order-product" wire:model.live="product_id"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">{{ $category_id === '' ? '— Elige primero la categoría —' : '— Selecciona un producto —' }}</option>
                    @foreach ($categoryProducts as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->is_active ? '' : ' (deshabilitado)' }}</option>
                    @endforeach
                    <option value="manual">Otro (captura manual)</option>
                </select>
                @error('product_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($selectedProduct)
            <div class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500">
                Tipo: <span class="font-medium text-slate-700">{{ $selectedProduct->typeLabel() }}</span>
                · Cobro: <span class="font-medium text-slate-700">{{ $selectedProduct->billing_mode?->label() }}</span>
                @if ($selectedProduct->isFromPlan())
                    · Plan: <span class="font-medium text-slate-700">{{ $selectedProduct->plan?->name }}</span>
                @endif
                <span class="block">El precio, el IVA y los ciclos se toman del producto. Puedes ajustarlos abajo.</span>
            </div>
        @elseif ($isManual)
            {{-- Captura manual (pedidos que no salen del catálogo). --}}
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="order-type" class="block text-sm font-medium text-slate-700">Tipo</label>
                    <select id="order-type" wire:model.live="type"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach ($typeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="order-name" class="block text-sm font-medium text-slate-700">Nombre del servicio</label>
                    <input id="order-name" type="text" wire:model="name"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </div>
        @endif
        @error('name') <p class="-mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

        {{-- Ejecutivo de ventas responsable de ESTE servicio. Un cliente puede
             tener varios servicios con ejecutivos distintos. --}}
        <div>
            <label class="block text-sm font-medium text-slate-700">Ejecutivo de ventas</label>
            <select wire:model="sales_rep_id"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">— Sin asignar —</option>
                @foreach ($salesReps as $rep)
                    <option value="{{ $rep->id }}">{{ $rep->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-500">El ejecutivo se asigna por servicio (no por cliente). Recibirá los avisos de vencimiento de este servicio.</p>
            @error('sales_rep_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Estado</label>
                <select wire:model="status"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            {{-- El servidor solo aplica a hosting. --}}
            @if ($usesServer)
                <div>
                    <label class="block text-sm font-medium text-slate-700">Servidor (opcional)</label>
                    <select wire:model="server_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Sin servidor</option>
                        @foreach ($servers as $server)
                            <option value="{{ $server->id }}">{{ $server->name }}</option>
                        @endforeach
                    </select>
                    @error('server_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        {{-- Dominio: solo para hosting. Ligar uno existente del cliente o agregar uno nuevo. --}}
        @if ($usesDomain)
            <div class="rounded-lg border border-slate-200 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-slate-700">Dominio</h3>
                </div>
                <p class="mt-0.5 text-xs text-slate-400">Liga este hosting con un dominio del cliente o agrega uno nuevo.</p>

                <div class="mt-3 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" wire:model.live="domainMode" value="existing"
                               class="border-slate-300 text-slate-800 focus:ring-slate-500">
                        Ligar dominio existente
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" wire:model.live="domainMode" value="new"
                               class="border-slate-300 text-slate-800 focus:ring-slate-500">
                        Agregar nuevo
                    </label>
                </div>

                @if ($domainMode === 'existing')
                    <div class="mt-3">
                        @if ($client_id === '')
                            <p class="text-sm text-slate-500">Selecciona primero un cliente para ver sus dominios.</p>
                        @elseif ($clientDomains->isEmpty())
                            <p class="text-sm text-slate-500">Este cliente no tiene dominios registrados. Usa “Agregar nuevo”.</p>
                        @else
                            <label class="block text-sm font-medium text-slate-700">Dominio del cliente</label>
                            <select wire:model="domainId"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">Selecciona un dominio</option>
                                @foreach ($clientDomains as $domain)
                                    <option value="{{ $domain->id }}">{{ $domain->name }}{{ $domain->service_id ? ' (ya ligado a otro servicio)' : '' }}</option>
                                @endforeach
                            </select>
                            @error('domainId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>
                @elseif ($domainMode === 'new')
                    <div class="mt-3 space-y-3">
                        <div class="grid gap-3 {{ $domainExtensions->isNotEmpty() ? 'sm:grid-cols-3' : '' }}">
                            <div class="{{ $domainExtensions->isNotEmpty() ? 'sm:col-span-2' : '' }}">
                                <label for="order-domain-name" class="block text-sm font-medium text-slate-700">Nuevo dominio</label>
                                <input id="order-domain-name" type="text" wire:model.live.debounce.400ms="domainName"
                                       placeholder="{{ $domainExtensions->isNotEmpty() ? 'ejemplo' : 'ejemplo.com' }}"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('domainName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            {{-- Extensiones dadas de alta en Productos (tipo "Dominio"). --}}
                            @if ($domainExtensions->isNotEmpty())
                                <div>
                                    <label for="order-domain-tld" class="block text-sm font-medium text-slate-700">Extensión</label>
                                    <select id="order-domain-tld" wire:model.live="domainTldId"
                                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                        <option value="">Otra (escríbela en el nombre)</option>
                                        @foreach ($domainExtensions as $ext)
                                            <option value="{{ $ext->id }}">{{ $ext->tld() }}</option>
                                        @endforeach
                                    </select>
                                    @error('domainTldId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>
                        <p class="-mt-1 text-xs text-slate-400">
                            Se creará como dominio del cliente ligado a este servicio{{ $this->fullDomainName() !== '' ? ': '.$this->fullDomainName() : '' }}.
                        </p>

                        {{-- Origen del dominio: ya lo tiene el cliente o se compra con nosotros. --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Origen</label>
                            <div class="mt-1 flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="radio" wire:model.live="domainOrigin" value="owned"
                                           class="border-slate-300 text-slate-800 focus:ring-slate-500">
                                    Ya lo tiene en otro registrador (sin costo)
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="radio" wire:model.live="domainOrigin" value="purchase"
                                           class="border-slate-300 text-slate-800 focus:ring-slate-500">
                                    Comprar con nosotros
                                </label>
                            </div>
                        </div>

                        @if ($domainOrigin === 'purchase')
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model.live="domainIsPremium"
                                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                                Dominio premium
                            </label>

                            @if ($this->domainPriceIsManual())
                                <div>
                                    <label for="order-domain-price" class="block text-sm font-medium text-slate-700">Precio del dominio (MXN)</label>
                                    <input id="order-domain-price" type="number" step="0.01" min="0" wire:model.live="domainPrice"
                                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                    <p class="mt-1 text-xs text-slate-400">Se suma al total del pedido.</p>
                                    @error('domainPrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @elseif ($this->domainIsChargeable())
                                <p class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    Precio de la extensión {{ $this->domainProduct()?->tld() }}:
                                    <span class="font-medium">{{ number_format($this->domainCost(), 2) }} MXN</span> (según Productos). Se suma al total del pedido.
                                </p>
                            @elseif ($this->domainIsFree())
                                <p class="rounded-md bg-green-50 px-3 py-2 text-sm font-medium text-green-700">
                                    Este dominio es gratis{{ $this->domainProduct() ? ' (extensión con precio 0 en Productos)' : ' (extensión .com / .com.mx no premium)' }}.
                                </p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Complementos del catálogo (Productos → Complementos) --}}
        @if ($catalogAddons->isNotEmpty())
            <fieldset class="rounded-lg border border-slate-200 p-4">
                <legend class="px-1 text-sm font-medium text-slate-700">Complementos (opcional)</legend>
                <p class="text-xs text-slate-400">Marca los complementos que quieras agregar a este pedido. El precio recurrente es el del ciclo elegido.</p>
                <div class="mt-3 space-y-2">
                    @foreach ($catalogAddons as $addon)
                        <label wire:key="order-addon-{{ $addon->id }}" class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50">
                            <span class="inline-flex items-center gap-2 text-slate-700">
                                <input type="checkbox" wire:model.live="selectedAddons.{{ $addon->id }}"
                                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                                {{ $addon->name }}
                                <span class="text-xs text-slate-400">{{ $addon->billing_mode?->label() }}</span>
                            </span>
                            <span class="text-slate-600">{{ number_format($addonPrices[$addon->id] ?? 0, 2) }} MXN</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endif

        <div>
            <label class="block text-sm font-medium text-slate-700">Ciclo de facturación</label>
            <select wire:model.live="billing_cycle"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @if (! empty($planCycles))
                    @foreach ($planCycles as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                @else
                    @foreach ($cycleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                @endif
            </select>
            @if (! empty($planCycles))
                <p class="mt-1 text-xs text-slate-400">Ciclos disponibles según el producto elegido.</p>
            @endif
            @error('billing_cycle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Precios --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Precio unitario (MXN)</label>
                <input type="number" step="0.01" min="0" wire:model.live="unit_price"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('unit_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">IVA (%)</label>
                <input type="number" step="0.01" min="0" wire:model.live="tax_rate"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('tax_rate') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Desglose por concepto (cada uno con su IVA) --}}
        <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Desglose del pedido</p>
            <table class="mt-2 w-full text-sm">
                <thead class="text-xs text-slate-400">
                    <tr>
                        <th class="py-1 text-left font-medium">Concepto</th>
                        <th class="py-1 text-right font-medium">Base</th>
                        <th class="py-1 text-right font-medium">IVA</th>
                        <th class="py-1 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/70">
                    @foreach ($this->breakdownPreview() as $row)
                        <tr>
                            <td class="py-1.5 text-slate-700">{{ $row['label'] }}</td>
                            <td class="py-1.5 text-right text-slate-600">{{ number_format($row['base'], 2) }}</td>
                            <td class="py-1.5 text-right text-slate-600">{{ number_format($row['tax'], 2) }}</td>
                            <td class="py-1.5 text-right font-medium text-slate-800">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-slate-300">
                        <td colspan="3" class="pt-2 text-right text-sm font-semibold text-slate-700">Total del pedido</td>
                        <td class="pt-2 text-right text-base font-semibold text-slate-900">{{ $this->totalPreview() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Fecha de inicio</label>
                <input type="date" wire:model.live="starts_at"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Fecha de vencimiento</label>
                <input type="date" wire:model.live="expires_at"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <p class="mt-1 text-xs text-slate-400">Se calcula automáticamente según inicio y ciclo. Puedes ajustarla.</p>
                @error('expires_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Notas</label>
            <textarea wire:model="notes" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear pedido' }}
            </button>
            <a href="{{ route('admin.services.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
