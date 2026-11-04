<div class="md:flex md:gap-6">
    @include('partials.account-sidebar', ['active' => 'profile'])

    <div class="mt-6 min-w-0 flex-1 md:mt-0">
        <h1 class="text-2xl font-semibold text-slate-900">Mis Datos</h1>

        {{-- Breadcrumb --}}
        <div class="mt-3 flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-slate-500 shadow-sm ring-1 ring-slate-900/5">
            <span>Área del Cliente</span>
            <span class="text-slate-300">›</span>
            <span class="font-medium text-indigo-600">Mis Datos</span>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="grid gap-5 sm:grid-cols-2">
                {{-- Columna izquierda --}}
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Nombre</label>
                    <input type="text" wire:model="name"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Dirección</label>
                    <input type="text" wire:model="address_line1" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('address_line1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Apellido</label>
                    <input type="text" wire:model="last_name"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Dirección (Continuación)</label>
                    <input type="text" wire:model="address_line2" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('address_line2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Empresa</label>
                    <input type="text" wire:model="company" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('company') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">País</label>
                    <select wire:model="country" @disabled($isSubUser)
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                        @foreach ($countries as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Dirección de E-Mail</label>
                    <input type="email" wire:model="email"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-slate-400">Es el correo con el que inicias sesión.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Código Postal</label>
                    <input type="text" wire:model="postal_code" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Teléfono</label>
                    <input type="text" wire:model="phone" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Estado</label>
                    <input type="text" wire:model="state" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-indigo-700">RFC / Identificación fiscal</label>
                    <input type="text" wire:model="tax_id" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('tax_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold text-indigo-700">Ciudad</label>
                    <input type="text" wire:model="city" @disabled($isSubUser)
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400">
                    @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @unless ($isSubUser)
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-semibold text-indigo-700">Contacto de Facturación Predeterminado</label>
                        <select wire:model="billing_contact"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($billingContacts as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">Quién recibe la correspondencia de facturación.</p>
                    </div>
                @endunless
            </div>

            @if ($isSubUser)
                <p class="mt-4 rounded-md bg-slate-50 px-4 py-2 text-xs text-slate-500">
                    Algunos datos de la cuenta (empresa, dirección y facturación) solo los puede editar el titular.
                </p>
            @endif

            <div class="mt-6">
                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>
