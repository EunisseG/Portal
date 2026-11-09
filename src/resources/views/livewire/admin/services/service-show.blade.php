<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $service->name }}</h1>
        @can('update', App\Domain\Services\Models\Service::class)
            @if ($service->isLockedByPayment())
                <span class="rounded-md bg-green-50 px-4 py-2 text-sm font-medium text-green-700 ring-1 ring-green-600/10"
                      title="El pedido ya está pagado y no se puede editar.">
                    Pagado
                </span>
            @else
                <a href="{{ route('admin.services.edit', $service) }}"
                   class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Editar
                </a>
            @endif
        @endcan
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
            {{ session('error') }}
        </div>
    @endif

    {{-- Hosting (WHM) — acciones manuales, solo si el servidor del servicio
         tiene una conexión WHM configurada --}}
    @if ($whmEnabled)
        @can('update', App\Domain\Services\Models\Service::class)
            <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-slate-900">Hosting (WHM)</h2>
                        <p class="text-xs text-slate-400">Servidor: {{ $service->server?->name }}</p>
                    </div>
                    @if ($whmAccount)
                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                            Cuenta: {{ $whmAccount }}
                        </span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Sin cuenta</span>
                    @endif
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @if (! $whmAccount)
                        <button wire:click="whmCreateAccount"
                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Crear cuenta en WHM
                        </button>
                        <button type="button" wire:click="$toggle('showLinkForm')"
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                            {{ $showLinkForm ? 'Cancelar' : 'Vincular cuenta existente' }}
                        </button>
                    @else
                        <button wire:click="whmSuspend"
                                wire:confirm="¿Suspender la cuenta de hosting?"
                                class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 hover:bg-amber-100">
                            Suspender
                        </button>
                        <button wire:click="whmUnsuspend"
                                class="rounded-md border border-green-300 bg-green-50 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-100">
                            Reactivar
                        </button>
                        <button wire:click="unlinkAccount"
                                wire:confirm="¿Desvincular esta cuenta cPanel del servicio? No se borra nada en el servidor."
                                class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Desvincular
                        </button>
                    @endif
                </div>

                {{-- Vincular una cuenta cPanel ya existente en el servidor --}}
                @if (! $whmAccount && $showLinkForm)
                    <form wire:submit="linkAccount" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50 p-4">
                        <div class="flex-1 min-w-[16rem]">
                            <label class="block text-xs font-medium text-slate-600">Usuario de cPanel existente</label>
                            <input type="text" wire:model="linkUsername" placeholder="p. ej. clientexyz"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <p class="mt-1 text-xs text-slate-400">Se verificará que la cuenta exista en {{ $service->server?->name }} antes de vincular.</p>
                            @error('linkUsername') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Verificar y vincular
                        </button>
                    </form>
                @endif
            </div>
        @endcan
    @endif

    {{-- Correos del hosting (solo lectura, consulta a cPanel bajo demanda) --}}
    @if ($canShowMailboxes)
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-medium text-slate-900">Correos</h2>
                    <p class="text-xs text-slate-400">Cuentas de correo de la cuenta cPanel y su uso.</p>
                </div>
                <button type="button" wire:click="loadMailboxes(true)"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ $mailboxes === null ? 'Consultar correos' : 'Actualizar' }}
                </button>
            </div>

            @if ($mailboxes !== null)
                @if (! $mailboxes['ok'])
                    <div class="mt-4 rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        {{ $mailboxes['message'] ?: 'No se pudieron obtener los correos.' }}
                    </div>
                @elseif (empty($mailboxes['mailboxes']))
                    <div class="mt-4 rounded-md bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                        Esta cuenta no tiene correos creados.
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
                                @foreach ($mailboxes['mailboxes'] as $box)
                                    <tr wire:key="ambox-{{ $loop->index }}" class="transition hover:bg-slate-50/70">
                                        <td class="px-4 py-2 font-medium text-slate-900">{{ $box['email'] }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ number_format($box['disk_used_mb'], 1) }} MB</td>
                                        <td class="px-4 py-2 text-slate-600">{{ $box['disk_quota_mb'] !== null ? number_format($box['disk_quota_mb'], 0).' MB' : 'Ilimitada' }}</td>
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
            @else
                <p class="mt-4 text-sm text-slate-500">Pulsa "Consultar correos" para ver los buzones de esta cuenta.</p>
            @endif
        </div>
    @endif

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-slate-500">Cliente</dt>
                <dd class="text-slate-800">{{ $service->client->user->name }} <span class="text-slate-400">({{ $service->client->company }})</span></dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Tipo</dt>
                <dd class="text-slate-800">{{ $service->type?->label() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Estado</dt>
                <dd>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">
                        {{ $service->status?->label() }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Servidor</dt>
                <dd class="text-slate-800">{{ $service->server?->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Plan</dt>
                <dd class="text-slate-800">{{ $service->plan?->name ?: 'Manual (sin plan)' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Ciclo</dt>
                <dd class="text-slate-800">{{ $service->billing_cycle?->label() ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Precio unitario</dt>
                <dd class="text-slate-800">{{ $service->unitPrice()->format() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">IVA</dt>
                <dd class="text-slate-800">{{ (float) $service->tax_rate }}%</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Vencimiento</dt>
                <dd class="text-slate-800">{{ $service->expires_at?->format('Y-m-d') ?: '—' }}</dd>
            </div>
        </dl>

        {{-- Desglose de precio del pedido --}}
        <div class="mt-6 border-t border-slate-100 pt-4">
            <dl class="ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Subtotal</dt>
                    <dd class="text-slate-800">{{ $service->subtotal()->format() }}</dd>
                </div>
                @if ($service->hasDiscount())
                    <div class="flex justify-between text-green-700">
                        <dt>Descuento{{ $service->promotion ? ' ('.$service->promotion->name.')' : '' }}</dt>
                        <dd>− {{ $service->discount()->format() }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-slate-500">IVA ({{ (float) $service->tax_rate }}%)</dt>
                    <dd class="text-slate-800">{{ $service->tax()->format() }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-1 text-base font-semibold text-slate-900">
                    <dt>Total</dt>
                    <dd>{{ $service->total()->format() }}</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Promoción / descuento --}}
    @can('update', App\Domain\Services\Models\Service::class)
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium text-slate-900">Promoción / descuento</h2>
                @if (! $service->promotion)
                    <button type="button" wire:click="$toggle('showPromotionForm')"
                            class="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline">
                        {{ $showPromotionForm ? 'Cancelar' : '+ Aplicar promoción' }}
                    </button>
                @endif
            </div>

            @if ($service->promotion)
                <div class="mt-4 flex items-center justify-between rounded-lg bg-green-50 px-4 py-3">
                    <div>
                        <div class="font-medium text-green-800">{{ $service->promotion->name }}
                            <span class="text-green-600">({{ $service->promotion->code }})</span>
                        </div>
                        <div class="text-xs text-green-700">
                            Descuento {{ $service->promotion->discountLabel() }} · aplicado: {{ $service->discount()->format() }}
                        </div>
                    </div>
                    <button type="button" wire:click="removePromotion"
                            wire:confirm="¿Quitar la promoción de este pedido?"
                            class="text-xs text-red-600 hover:underline">Quitar</button>
                </div>
            @elseif ($showPromotionForm)
                <form wire:submit="applyPromotion" class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-slate-50 p-4">
                    <div class="flex-1 min-w-[16rem]">
                        <label class="block text-xs font-medium text-slate-600">Promoción vigente</label>
                        <select wire:model="promotionId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                            <option value="">Selecciona una promoción...</option>
                            @foreach ($promotions as $promo)
                                <option value="{{ $promo->id }}">{{ $promo->name }} — {{ $promo->discountLabel() }}</option>
                            @endforeach
                        </select>
                        @error('promotionId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Aplicar
                    </button>
                </form>
            @else
                <p class="mt-2 text-sm text-slate-500">Sin promoción aplicada.</p>
            @endif
        </div>
    @endcan

    {{-- Facturación del pedido --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-900">Facturación</h2>
            @can('create', App\Domain\Billing\Models\Invoice::class)
                <button type="button" wire:click="generateInvoice"
                        wire:confirm="Se generará una factura pendiente con el total del pedido ({{ $service->total()->format() }}). ¿Continuar?"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Generar factura desde el pedido
                </button>
            @endcan
        </div>

        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($service->invoices as $invoice)
                <div class="flex items-center justify-between py-3" wire:key="svc-invoice-{{ $invoice->id }}">
                    <div>
                        <div class="font-medium text-slate-800">{{ $invoice->folio ?: 'Sin folio' }}</div>
                        <div class="text-xs text-slate-500">
                            Total {{ $invoice->total()->format() }} · Pagado {{ $invoice->amountPaid()->format() }} · Saldo {{ $invoice->balance()->format() }}
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">
                            {{ $invoice->status?->label() }}
                        </span>
                        @can('update', App\Domain\Billing\Models\Invoice::class)
                            <a href="{{ route('admin.invoices.edit', $invoice) }}" class="text-xs text-slate-600 hover:underline">Editar</a>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-3 text-sm text-slate-500">Este pedido aún no tiene facturas generadas.</p>
            @endforelse
        </div>
    </div>

    {{-- Complementos --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-900">Complementos</h2>
            @can('create', App\Domain\Services\Models\Addon::class)
                <button type="button" wire:click="$toggle('showAddonForm')"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 hover:underline">
                    {{ $showAddonForm ? 'Cancelar' : '+ Agregar complemento' }}
                </button>
            @endcan
        </div>

        @if ($showAddonForm)
            <form wire:submit="addAddon" class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <div class="inline-flex rounded-md bg-white p-0.5 text-sm ring-1 ring-slate-200">
                        <button type="button" wire:click="$set('addonMode', 'catalog')"
                                class="rounded px-3 py-1 {{ $addonMode === 'catalog' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">
                            Del catálogo
                        </button>
                        <button type="button" wire:click="$set('addonMode', 'custom')"
                                class="rounded px-3 py-1 {{ $addonMode === 'custom' ? 'bg-slate-900 text-white' : 'text-slate-600' }}">
                            Personalizado
                        </button>
                    </div>
                </div>

                @if ($addonMode === 'catalog')
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-600">Complemento del catálogo</label>
                        <select wire:model.live="addonCatalogId" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                            <option value="">Selecciona un complemento...</option>
                            @foreach ($catalogItems as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} — {{ $item->price()->format() }}</option>
                            @endforeach
                        </select>
                        @error('addonCatalogId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @if ($catalogItems->isEmpty())
                            <p class="mt-1 text-xs text-slate-500">No hay complementos en el catálogo. Puedes crearlos en Productos → Complementos, o usar el modo personalizado.</p>
                        @endif
                    </div>

                    {{-- Selector de ciclo: solo si el complemento elegido es recurrente --}}
                    @if ($selectedCatalogItem && $selectedCatalogItem->isRecurring())
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600">Ciclo de facturación</label>
                            <select wire:model.live="addonCycle" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                <option value="">Selecciona un ciclo...</option>
                                @foreach ($selectedCatalogItem->prices as $price)
                                    <option value="{{ $price->billing_cycle->value }}">
                                        {{ $price->billing_cycle->label() }} — {{ $price->unitPrice()->format() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('addonCycle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-slate-500">El IVA del pedido se aplica sobre el subtotal; aquí se congela el precio del ciclo elegido.</p>
                        </div>
                    @endif
                @else
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nombre</label>
                        <input type="text" wire:model="addonName"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        @error('addonName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Precio (MXN)</label>
                        <input type="number" step="0.01" min="0" wire:model="addonPrice"
                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        @error('addonPrice') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label class="block text-xs font-medium text-slate-600">Estado</label>
                    <select wire:model="addonStatus" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        @foreach ($addonStatusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600">Vencimiento</label>
                    <input type="date" wire:model="addonExpiresAt"
                           class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    @error('addonExpiresAt') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                        Guardar complemento
                    </button>
                </div>
            </form>
        @endif

        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($service->addons as $addon)
                <div class="flex items-center justify-between py-3" wire:key="addon-{{ $addon->id }}">
                    <div>
                        <div class="font-medium text-slate-800">{{ $addon->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $addon->price()->format() }}@if ($addon->billing_cycle) · {{ $addon->billing_cycle->label() }}@endif · Vence {{ $addon->expires_at?->format('Y-m-d') ?: '—' }}
                            @if ($addon->isExpired())
                                <span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 font-medium text-red-600">Vencido</span>
                            @elseif ($addon->billing_cycle && $addon->daysUntilExpiry() !== null && $addon->daysUntilExpiry() <= 30)
                                <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-600">
                                    {{ $addon->daysUntilExpiry() === 0 ? 'Vence hoy' : 'En '.$addon->daysUntilExpiry().' días' }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $addon->status?->badgeClasses() }}">
                            {{ $addon->status?->label() }}
                        </span>
                        @if ($addon->isRecurring())
                            @can('update', App\Domain\Services\Models\Service::class)
                                <button type="button" wire:click="renewAddon({{ $addon->id }})"
                                        class="text-xs text-indigo-600 hover:underline">Renovar</button>
                            @endcan
                        @endif
                        @can('delete', App\Domain\Services\Models\Addon::class)
                            <button type="button" wire:click="deleteAddon({{ $addon->id }})"
                                    wire:confirm="¿Eliminar este complemento?"
                                    class="text-xs text-red-600 hover:underline">Eliminar</button>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-3 text-sm text-slate-500">Este servicio no tiene complementos.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.services.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a servicios</a>
    </div>
</div>
