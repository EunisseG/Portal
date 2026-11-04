<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar cliente' : 'Nuevo cliente' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Datos de acceso</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nombre</label>
                <input type="text" wire:model="name"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Apellido</label>
                <input type="text" wire:model="last_name"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Correo</label>
            <input type="email" wire:model="email"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Correo secundario (recuperación)</label>
            <input type="email" wire:model="secondary_email" placeholder="Opcional"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            <p class="mt-1 text-xs text-slate-400">Correo de contacto/recuperación. No se usa para iniciar sesión.</p>
            @error('secondary_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @unless ($this->isEditing())
            <p class="rounded-md bg-blue-50 px-4 py-2 text-sm text-blue-700 ring-1 ring-blue-600/10">
                No necesitas fijar una contraseña: al crear el cliente se le enviará un correo con un enlace para que establezca la suya.
            </p>
        @endunless

        <h2 class="border-t border-slate-100 pt-5 text-sm font-semibold uppercase tracking-wide text-slate-400">Datos del cliente</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Empresa</label>
                <input type="text" wire:model="company"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('company') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">RFC / Tax ID</label>
                <input type="text" wire:model="tax_id"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('tax_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Teléfono</label>
                <input type="text" wire:model="phone"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">WhatsApp</label>
                <input type="text" wire:model="whatsapp"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('whatsapp') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Dirección</label>
                <input type="text" wire:model="address_line1"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('address_line1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Dirección (continuación)</label>
                <input type="text" wire:model="address_line2"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('address_line2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Ciudad</label>
                <input type="text" wire:model="city"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Estado</label>
                <input type="text" wire:model="state"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Código Postal</label>
                <input type="text" wire:model="postal_code"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">País</label>
                <select wire:model="country"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Contacto de facturación predeterminado</label>
            <select wire:model="billing_contact"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @foreach ($billingContacts as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-slate-400">Quién recibe la correspondencia de facturación.</p>
            @error('billing_contact') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Dirección de facturación (texto libre)</label>
            <textarea wire:model="billing_address" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            <p class="mt-1 text-xs text-slate-400">Campo heredado; opcional si ya capturaste la dirección estructurada arriba.</p>
            @error('billing_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @unless ($this->isEditing())
            <div class="border-t border-slate-100 pt-5">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" wire:model.live="addProduct"
                           class="rounded border-slate-300 text-slate-800 focus:ring-slate-500">
                    Añadir un producto / servicio inicial
                </label>

                @if ($addProduct)
                    <div class="mt-3">
                        @if ($products->isEmpty())
                            <p class="text-sm text-slate-500">
                                No hay productos activos en el catálogo.
                                <a href="{{ route('admin.products.index') }}" class="text-slate-600 hover:text-slate-900 hover:underline">Crear producto</a>
                            </p>
                        @else
                            <label class="block text-sm font-medium text-slate-700">Producto del catálogo</label>
                            <select wire:model.live="productId"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <option value="">— Selecciona un producto —</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->name }}
                                        @if ($product->isRecurring()) · recurrente @else · {{ $product->price()->format() }} (pago único) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('productId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            @if ($selectedProduct && $selectedProduct->isRecurring())
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-slate-700">Ciclo de facturación</label>
                                    <select wire:model="productCycle"
                                            class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                        @foreach ($selectedProduct->prices as $price)
                                            <option value="{{ $price->billing_cycle->value }}">
                                                {{ $price->billing_cycle->label() }} · {{ $price->total()->format() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-400">Se creará el servicio con el precio del ciclo elegido.</p>
                                </div>
                            @else
                                <p class="mt-1 text-xs text-slate-400">Se creará un servicio activo con los datos del producto.</p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        @endunless

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear cliente' }}
            </button>
            <a href="{{ route('admin.clients.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
