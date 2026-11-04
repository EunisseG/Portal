<div>
    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h1 class="text-lg font-semibold text-slate-900">Productos</h1>
        <p class="mt-1 text-sm text-slate-500">Gestiona productos, prepara nuevas ofertas y organiza las categorías de producto.</p>

        {{-- Pestañas --}}
        <div class="mt-4 flex gap-1 border-b border-slate-200">
            <button type="button" wire:click="setTab('products')"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'products' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Productos
            </button>
            <button type="button" wire:click="setTab('categories')"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'categories' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Categorías
            </button>
            <button type="button" wire:click="setTab('types')"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ $tab === 'types' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                Tipos
            </button>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
        @endif

        {{-- ===================== Pestaña: Productos ===================== --}}
        @if ($tab === 'products')
            {{-- Tarjetas KPI por estado --}}
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 text-lg font-semibold text-green-600">{{ $kpis['active'] }}</span>
                    <span class="text-sm font-medium text-slate-600">Habilitados</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-lg font-semibold text-slate-600">{{ $kpis['inactive'] }}</span>
                    <span class="text-sm font-medium text-slate-600">Deshabilitados</span>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-lg font-semibold text-blue-600">{{ $kpis['total'] }}</span>
                    <span class="text-sm font-medium text-slate-600">Total</span>
                </div>
            </div>

            {{-- Gráfica de distribución de productos por estado (barras) --}}
            <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-semibold text-slate-900">Distribución de productos por estado</h2>
                @if ($kpis['total'] > 0)
                    <div class="mt-4 h-64">
                        <canvas data-status-chart
                                data-labels='@json($chart['labels'])'
                                data-values='@json($chart['data'])'></canvas>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-500">Aún no hay productos para graficar.</p>
                @endif
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <x-admin.search-input class="max-w-sm flex-1" width="w-full"
                    placeholder="Introduzca el texto a buscar..." :hint="false" />
                <button type="button" wire:click="openProductModal"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nuevo producto
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">Número</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Categoría</th>
                            <th class="px-3 py-3">Título</th>
                            <th class="px-3 py-3">Cobro</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3">Prioridad</th>
                            <th class="px-3 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-3 py-3 text-slate-600">{{ $product->id }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $product->typeLabel() }}</td>
                                <td class="px-3 py-3 text-indigo-600">{{ $product->category?->title ?: '—' }}</td>
                                <td class="px-3 py-3 font-medium text-slate-900">
                                    {{ $product->name }}
                                    @if ($product->isFromPlan())
                                        <span class="ml-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Plan</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-600">
                                    @if ($product->isRecurring())
                                        <div class="text-xs">
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Recurrente</span>
                                            <div class="mt-1 space-y-0.5">
                                                @forelse ($product->prices as $price)
                                                    <div>{{ $price->billing_cycle?->label() }}: {{ $price->total()->format() }}</div>
                                                @empty
                                                    <div class="text-slate-400">Sin precios</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-xs">
                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Pago único</span>
                                            <div class="mt-1">{{ $product->price()->format() }}</div>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($product->is_active)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Habilitado</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Deshabilitado</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <input type="number" min="1" wire:model="priorities.{{ $product->id }}"
                                           class="w-16 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($product->isFromPlan())
                                            {{-- Producto espejo de un plan: se edita desde el plan. --}}
                                            <a href="{{ route('admin.plans.edit', $product->plan_id) }}" title="Editar el plan"
                                               class="rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 hover:underline">
                                                Editar plan
                                            </a>
                                        @else
                                            <button type="button" wire:click="editProduct({{ $product->id }})" title="Editar"
                                                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                            </button>
                                            <button type="button" wire:click="deleteProduct({{ $product->id }})" wire:confirm="¿Eliminar este producto?" title="Eliminar"
                                                    class="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.68.1 1.02.16M18.16 5.79 17.05 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.11 48.11 0 0 0-3.48-.4m-11 .55c.34-.06.68-.11 1.02-.16m0 0a48.11 48.11 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-slate-500">No hay productos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->isNotEmpty())
                <div class="mt-4 flex justify-end">
                    <button type="button" wire:click="updatePriorities"
                            class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                        Actualizar prioridad
                    </button>
                </div>
            @endif
        @endif

        {{-- ===================== Pestaña: Categorías ===================== --}}
        @if ($tab === 'categories')
            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="openCategoryModal"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nueva Categoría
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">#</th>
                            <th class="px-3 py-3">Título</th>
                            <th class="px-3 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($categories as $category)
                            <tr wire:key="category-{{ $category->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-3 py-3 text-slate-600">{{ $category->id }}</td>
                                <td class="px-3 py-3 font-medium text-indigo-600">{{ $category->title }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="editCategory({{ $category->id }})" title="Editar"
                                                class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                        </button>
                                        <button type="button" wire:click="deleteCategory({{ $category->id }})" wire:confirm="¿Eliminar esta categoría?" title="Eliminar"
                                                class="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.68.1 1.02.16M18.16 5.79 17.05 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.11 48.11 0 0 0-3.48-.4m-11 .55c.34-.06.68-.11 1.02-.16m0 0a48.11 48.11 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-8 text-center text-slate-500">No hay categorías.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ===================== Pestaña: Tipos ===================== --}}
        @if ($tab === 'types')
            <div class="mt-4 flex justify-end">
                <button type="button" wire:click="openTypeModal"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Nuevo tipo
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">#</th>
                            <th class="px-3 py-3">Tipo</th>
                            <th class="px-3 py-3">Identificador</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($productTypes as $type)
                            <tr wire:key="ptype-{{ $type->id }}" class="transition hover:bg-slate-50/70">
                                <td class="px-3 py-3 text-slate-600">{{ $type->id }}</td>
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $type->label }}</td>
                                <td class="px-3 py-3 text-slate-500">{{ $type->slug }}</td>
                                <td class="px-3 py-3">
                                    @if ($type->is_active)
                                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="editType({{ $type->id }})" title="Editar"
                                                class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                        </button>
                                        <button type="button" wire:click="deleteType({{ $type->id }})" wire:confirm="¿Eliminar este tipo?" title="Eliminar"
                                                class="rounded-md p-2 text-red-500 hover:bg-red-50 hover:text-red-700">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.23c.34.05.68.1 1.02.16M18.16 5.79 17.05 19.67a2.25 2.25 0 0 1-2.24 2.08H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.11 48.11 0 0 0-3.48-.4"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-8 text-center text-slate-500">No hay tipos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===================== Modal: Producto ===================== --}}
    @if ($showProductModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">{{ $editingProductId ? 'Editar producto' : 'Crear nuevo producto' }}</h2>
                    <button type="button" wire:click="$set('showProductModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="saveProduct" class="space-y-4 px-5 py-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Tipo</label>
                        <select wire:model="productType" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('productType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Categoría</label>
                        <select wire:model="productCategoryId" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">— Sin categoría —</option>
                            @foreach ($categoryOptions as $id => $title)
                                <option value="{{ $id }}">{{ $title }}</option>
                            @endforeach
                        </select>
                        @error('productCategoryId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Título</label>
                        <input type="text" wire:model="productName" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('productName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Modo de cobro --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Modo de cobro</label>
                        <select wire:model.live="billingMode" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
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

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="$set('showProductModal', false)"
                                class="rounded-md bg-slate-500 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600">Cancelar</button>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            {{ $editingProductId ? 'Guardar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===================== Modal: Categoría ===================== --}}
    @if ($showCategoryModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">{{ $editingCategoryId ? 'Editar categoría' : 'Crear nueva categoría' }}</h2>
                    <button type="button" wire:click="$set('showCategoryModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="saveCategory" class="space-y-4 px-5 py-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Título</label>
                        <input type="text" wire:model="categoryTitle" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('categoryTitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">URL del icono</label>
                        <input type="text" wire:model="categoryIconUrl" placeholder="https://..." class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('categoryIconUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Descripción</label>
                        <textarea wire:model="categoryDescription" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        @error('categoryDescription') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="$set('showCategoryModal', false)"
                                class="rounded-md bg-slate-500 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600">Cancelar</button>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            {{ $editingCategoryId ? 'Guardar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===================== Modal: Tipo de producto ===================== --}}
    @if ($showTypeModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">{{ $editingTypeId ? 'Editar tipo' : 'Nuevo tipo de producto' }}</h2>
                    <button type="button" wire:click="$set('showTypeModal', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <form wire:submit="saveType" class="space-y-4 px-5 py-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Nombre del tipo</label>
                        <input type="text" wire:model="typeLabel" placeholder="Plugin, SSL, Addon..."
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('typeLabel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="typeActive" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Activo (disponible para asignar a productos)
                    </label>
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="$set('showTypeModal', false)"
                                class="rounded-md bg-slate-500 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600">Cancelar</button>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            {{ $editingTypeId ? 'Guardar' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        @include('partials.status-bar-chart-script')
    @endpush
</div>
