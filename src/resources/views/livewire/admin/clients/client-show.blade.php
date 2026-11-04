<div class="mx-auto max-w-4xl">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $client->user->fullName() ?: $client->user->name }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.clients.panel', $client) }}"
               class="rounded-md border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-200">
                Ir al panel de cliente
            </a>
            @can('create', App\Domain\Services\Models\Service::class)
                <a href="{{ route('admin.services.create', ['client' => $client->id]) }}"
                   class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    + Contratar servicio
                </a>
            @endcan
            @can('update', App\Domain\Clients\Models\Client::class)
                @if ($client->user->secondary_email)
                    {{-- Tiene correo secundario: el admin elige el destino. --}}
                    <button type="button" wire:click="startResend"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Reenviar contraseña
                    </button>
                @else
                    {{-- Sin secundario: envío directo al principal. --}}
                    <button type="button" wire:click="resendPasswordLink"
                            wire:confirm="¿Enviar al cliente un enlace para restablecer su contraseña?"
                            class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Reenviar contraseña
                    </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Selector de destino para reenviar la contraseña (si hay secundario) --}}
    @if ($showResendForm)
        <div class="mt-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-sm font-semibold text-slate-900">¿A qué correo enviamos el enlace?</h2>
            <div class="mt-3 space-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" wire:model="resendTarget" value="primary"
                           class="border-slate-300 text-slate-800 focus:ring-slate-500">
                    Principal — {{ $client->user->email }}
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" wire:model="resendTarget" value="secondary"
                           class="border-slate-300 text-slate-800 focus:ring-slate-500">
                    Secundario — {{ $client->user->secondary_email }}
                </label>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <button type="button" wire:click="resendPasswordLink"
                        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Enviar enlace
                </button>
                <button type="button" wire:click="cancelResend"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancelar
                </button>
            </div>
        </div>
    @endif

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif

    {{-- Pestañas de secciones --}}
    <div class="mt-6 border-b border-slate-200">
        <nav class="-mb-px flex flex-wrap gap-6 text-sm font-medium">
            @foreach ([
                'datos' => 'Datos',
                'servicios' => 'Servicios contratados',
                'dominios' => 'Dominios',
                'facturas' => 'Facturas',
                'tickets' => 'Tickets',
                'sla' => 'SLA',
            ] as $value => $label)
                <button type="button" wire:click="setTab('{{ $value }}')"
                        class="border-b-2 px-1 pb-3 {{ $tab === $value ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ============ Pestaña: Datos ============ --}}
    @if ($tab === 'datos')
    <div class="mt-6 space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        {{-- ---------- Formulario de datos (edición en línea) ---------- --}}
        <form wire:submit="save" class="space-y-6">
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Cuenta</h2>
                    <span>
                        @if ($client->user->isBlocked())
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Bloqueado</span>
                        @else
                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                        @endif
                    </span>
                </div>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Correo</label>
                        <input type="email" wire:model="email"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Correo secundario (recuperación)</label>
                        <input type="email" wire:model="secondary_email"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('secondary_email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Datos del cliente</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
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
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Contacto de facturación predeterminado</label>
                        <select wire:model="billing_contact"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach ($billingContacts as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('billing_contact') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Dirección de facturación (texto libre)</label>
                        <textarea wire:model="billing_address" rows="2"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
                        <p class="mt-1 text-xs text-slate-400">Campo heredado; opcional si ya capturaste la dirección estructurada arriba.</p>
                        @error('billing_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            @can('update', App\Domain\Clients\Models\Client::class)
                <div class="border-t border-slate-100 pt-6">
                    <button type="submit"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Guardar cambios
                    </button>
                    <span wire:loading wire:target="save" class="ml-2 text-sm text-slate-400">Guardando…</span>
                </div>
            @endcan
        </form>

        {{-- ---------- Autenticación en dos pasos del titular (fuera del form) ---------- --}}
        <div class="border-t border-slate-100 pt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Autenticación en dos pasos (2FA)</h2>
            <div class="mt-2 flex items-center gap-3">
                @if ($client->user->hasEnabledTwoFactorAuthentication())
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        Activo
                    </span>
                    @can('update', App\Domain\Clients\Models\Client::class)
                        <button type="button" wire:click="disableTwoFactor({{ $client->user->id }})"
                                wire:confirm="¿Desactivar el 2FA del titular? Podrá volver a configurarlo desde su cuenta."
                                class="rounded-md border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100">
                            Desactivar
                        </button>
                    @endcan
                @else
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactivo</span>
                @endif
            </div>
        </div>

        {{-- Sub-cuentas (sub-usuarios del titular) --}}
        <div class="border-t border-slate-100 pt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Sub-cuentas</h2>
            @if ($client->members->isNotEmpty())
                <div class="mt-3 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-2">Nombre</th>
                                <th class="px-4 py-2">Correo</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2">2FA</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($client->members as $member)
                                <tr wire:key="sub-{{ $member->id }}" class="transition hover:bg-slate-50/70">
                                    <td class="px-4 py-2 font-medium text-slate-900">{{ $member->fullName() ?: $member->name }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ $member->email }}</td>
                                    <td class="px-4 py-2">
                                        @if ($member->isBlocked())
                                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Bloqueado</span>
                                        @else
                                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            @if ($member->hasEnabledTwoFactorAuthentication())
                                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activo</span>
                                                @can('update', App\Domain\Clients\Models\Client::class)
                                                    <button type="button" wire:click="disableTwoFactor({{ $member->id }})"
                                                            wire:confirm="¿Desactivar el 2FA de este sub-usuario?"
                                                            class="rounded-md border border-red-300 bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 hover:bg-red-100">
                                                        Desactivar
                                                    </button>
                                                @endcan
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inactivo</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-2 text-sm text-slate-500">Este cliente no tiene sub-cuentas.</p>
            @endif
        </div>

        <div class="border-t border-slate-100 pt-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Saldo a favor</h2>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $client->creditBalance()->format() }}</p>
                </div>
                @can('manageCredit', $client)
                    <a href="{{ route('admin.clients.credit', $client) }}"
                       class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Gestionar saldo
                    </a>
                @endcan
            </div>
        </div>

        <div class="border-t border-slate-100 pt-4">
            <a href="{{ route('admin.clients.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a clientes</a>
        </div>
    </div>
    @endif

    {{-- ============ Pestaña: Servicios contratados ============ --}}
    @if ($tab === 'servicios')
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-lg font-medium text-slate-900">Servicios contratados</h2>
        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Servicio</th>
                        <th class="px-4 py-2">Tipo</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2">Vence</th>
                        <th class="px-4 py-2">Complementos</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($client->services as $service)
                        <tr wire:key="cs-{{ $service->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $service->name }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $service->type?->label() }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">
                                    {{ $service->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $service->expires_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $service->addons->count() }}</td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.services.show', $service) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Este cliente no tiene servicios contratados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============ Pestaña: Dominios ============ --}}
    @if ($tab === 'dominios')
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-lg font-medium text-slate-900">Dominios</h2>
        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Dominio</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2">Vence</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($client->domains as $domain)
                        <tr wire:key="cd-{{ $domain->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $domain->name }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $domain->status?->badgeClasses() }}">
                                    {{ $domain->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-slate-600">{{ $domain->expires_at?->format('Y-m-d') ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">Este cliente no tiene dominios.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============ Pestaña: Facturas ============ --}}
    @if ($tab === 'facturas')
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-900">Facturas</h2>
            @can('create', App\Domain\Billing\Models\Invoice::class)
                <a href="{{ route('admin.invoices.create', ['client' => $client->id]) }}"
                   class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                    + Subir factura
                </a>
            @endcan
        </div>
        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Folio</th>
                        <th class="px-4 py-2">Total</th>
                        <th class="px-4 py-2">Emisión</th>
                        <th class="px-4 py-2">Vence</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($client->invoices as $invoice)
                        <tr wire:key="ci-{{ $invoice->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $invoice->folio ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-800">{{ $invoice->total()->format() }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $invoice->issued_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $invoice->due_at?->format('Y-m-d') ?: '—' }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $invoice->status?->badgeClasses() }}">
                                    {{ $invoice->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.invoices.download', $invoice) }}" class="text-slate-600 hover:underline">PDF</a>
                                @can('update', App\Domain\Billing\Models\Invoice::class)
                                    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="ml-3 text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">Este cliente no tiene facturas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============ Pestaña: Tickets ============ --}}
    @if ($tab === 'tickets')
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-slate-900">Tickets</h2>
            @can('tickets.manage')
                <a href="{{ route('admin.tickets.create', ['client' => $client->id]) }}"
                   class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
                    Abrir ticket
                </a>
            @endcan
        </div>
        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Número</th>
                        <th class="px-4 py-2">Asunto</th>
                        <th class="px-4 py-2">Prioridad</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($client->tickets as $ticket)
                        <tr wire:key="ct-{{ $ticket->id }}" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $ticket->number }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $ticket->subject }}</td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->priority?->badgeClasses() }}">
                                    {{ $ticket->priority?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $ticket->status?->badgeClasses() }}">
                                    {{ $ticket->status?->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-500">Este cliente no tiene tickets.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============ Pestaña: SLA ============ --}}
    @if ($tab === 'sla')
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="text-lg font-medium text-slate-900">SLA del cliente</h2>
        <p class="mt-1 text-sm text-slate-500">
            Define tiempos de SLA específicos para este cliente (en minutos). Si una criticidad no tiene valor propio,
            se usa el SLA global. Los cambios aplican a los tickets de este cliente.
        </p>

        <div class="mt-4 overflow-hidden rounded-lg ring-1 ring-slate-900/5">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Criticidad</th>
                        <th class="px-4 py-2">1ª respuesta (min)</th>
                        <th class="px-4 py-2">Resolución (min)</th>
                        <th class="px-4 py-2">Global</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($slaRows as $row)
                        <tr wire:key="sla-{{ $row['value'] }}">
                            <td class="px-4 py-2 font-medium text-slate-900">{{ $row['label'] }}</td>
                            <td class="px-4 py-2">
                                <input type="number" min="1" wire:model="slaOverrides.{{ $row['value'] }}.first"
                                       placeholder="Global"
                                       class="w-28 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       @disabled(! $canManageSla)>
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" min="1" wire:model="slaOverrides.{{ $row['value'] }}.resolution"
                                       placeholder="Global"
                                       class="w-28 rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       @disabled(! $canManageSla)>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-400">
                                {{ $row['global_first'] ?? '—' }} / {{ $row['global_resolution'] ?? '—' }} min
                                @if ($row['has_override'])
                                    <span class="ml-1 rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700">Override</span>
                                @else
                                    <span class="ml-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">Usa global</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($canManageSla)
                                        <button type="button" wire:click="saveSlaOverride('{{ $row['value'] }}')" class="text-indigo-600 hover:underline">Guardar</button>
                                        @if ($row['has_override'])
                                            <button type="button" wire:click="clearSlaOverride('{{ $row['value'] }}')"
                                                    wire:confirm="¿Restablecer al SLA global para {{ $row['label'] }}?"
                                                    class="text-red-600 hover:underline">Usar global</button>
                                        @endif
                                    @else
                                        <span class="text-xs text-slate-400">Solo lectura</span>
                                    @endif
                                </div>
                                @error('sla_'.$row['value']) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
