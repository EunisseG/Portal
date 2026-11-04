<div>
    <h1 class="text-2xl font-semibold text-slate-900">Complementos</h1>
    <p class="mt-1 text-slate-600">Administra el catálogo de complementos y revisa los que ya están desplegados en pedidos.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    {{-- Pestañas --}}
    <div class="mt-6 border-b border-slate-200">
        <nav class="-mb-px flex gap-6 text-sm font-medium">
            <button type="button" wire:click="setTab('catalog')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'catalog' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Catálogo
            </button>
            <button type="button" wire:click="setTab('deployed')"
                    class="border-b-2 px-1 pb-3 {{ $tab === 'deployed' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Desplegados
            </button>
        </nav>
    </div>

    {{-- ===================== Pestaña: Catálogo ===================== --}}
    @if ($tab === 'catalog')
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            {{-- Formulario --}}
            <div class="lg:col-span-1">
                <form wire:submit="save" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Editar complemento' : 'Nuevo complemento' }}</h2>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Nombre</label>
                            <input type="text" wire:model="name" placeholder="Respaldo diario, IP dedicada..."
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Descripción</label>
                            <textarea wire:model="description" rows="2"
                                      class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Modo de cobro --}}
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Modo de cobro</label>
                            <select wire:model.live="billingMode"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @foreach ($billingModeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('billingMode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        {{-- Pago único: un solo precio --}}
                        @if ($billingMode === 'one_time')
                            <div>
                                <label class="block text-sm font-medium text-slate-700">Precio (MXN)</label>
                                <input type="number" step="0.01" min="0" wire:model="oneTimePrice"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @error('oneTimePrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @else
                            {{-- Recurrente: precios por ciclo --}}
                            <div>
                                <p class="text-sm font-medium text-slate-700">Precios por ciclo</p>
                                @error('enabled') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                <div class="mt-2 space-y-2">
                                    @foreach ($cycles as $cycle)
                                        @php $c = $cycle->value; @endphp
                                        <div class="rounded-lg border border-slate-200 p-3">
                                            <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                                <input type="checkbox" wire:model.live="enabled.{{ $c }}"
                                                       class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                                                {{ $cycle->label() }}
                                            </label>
                                            @if ($enabled[$c] ?? false)
                                                <div class="mt-2 grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-xs text-slate-500">Precio (MXN)</label>
                                                        <input type="number" step="0.01" min="0" wire:model="unitPrice.{{ $c }}"
                                                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                                        @error("unitPrice.$c") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs text-slate-500">IVA (%)</label>
                                                        <input type="number" step="0.01" min="0" wire:model="taxRate.{{ $c }}"
                                                               class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                                        @error("taxRate.$c") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                            Activo (disponible para agregar a pedidos)
                        </label>
                    </div>

                    <div class="mt-5 flex items-center gap-2">
                        <button type="submit"
                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            {{ $editingId ? 'Guardar cambios' : 'Crear complemento' }}
                        </button>
                        @if ($editingId)
                            <button type="button" wire:click="cancel"
                                    class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Cancelar
                            </button>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Listado del catálogo --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nombre</th>
                                <th class="px-4 py-3">Cobro</th>
                                <th class="px-4 py-3">Precio</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items as $item)
                                <tr wire:key="catalog-{{ $item->id }}" class="transition hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">{{ $item->name }}</div>
                                        @if ($item->description)
                                            <div class="text-xs text-slate-500">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $item->billing_mode?->label() ?? 'Pago único' }}</td>
                                    <td class="px-4 py-3 text-slate-800">
                                        @if ($item->isRecurring())
                                            <div class="space-y-0.5">
                                                @forelse ($item->prices as $price)
                                                    <div class="text-xs">
                                                        <span class="text-slate-500">{{ $price->billing_cycle->label() }}:</span>
                                                        {{ $price->unitPrice()->format() }}
                                                    </div>
                                                @empty
                                                    <span class="text-xs text-slate-400">Sin precios</span>
                                                @endforelse
                                            </div>
                                        @else
                                            {{ $item->price()->format() }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($item->is_active)
                                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-3">
                                            <button wire:click="edit({{ $item->id }})" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</button>
                                            <button wire:click="delete({{ $item->id }})"
                                                    wire:confirm="¿Eliminar este complemento del catálogo?"
                                                    class="text-red-600 hover:underline">Borrar</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-500">Aún no hay complementos en el catálogo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Pestaña: Desplegados ===================== --}}
    @if ($tab === 'deployed')
        <div class="mt-6 flex flex-wrap gap-3">
            <x-admin.search-input placeholder="Buscar por complemento o servicio..." :hint="false" />
            <select wire:model.live="statusFilter"
                    class="rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">Todos los estados</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Complemento</th>
                        <th class="px-4 py-3">Servicio</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3 text-right">Precio</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Vence</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($deployed as $addon)
                        <tr wire:key="addon-{{ $addon->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $addon->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $addon->service?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $addon->service?->client?->user?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-800">{{ $addon->price()->format() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $addon->status?->badgeClasses() }}">
                                    {{ $addon->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $addon->expires_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($addon->service)
                                    <a href="{{ route('admin.services.show', $addon->service) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Ver servicio</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">No hay complementos que coincidan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $deployed->links() }}
        </div>
    @endif
</div>
