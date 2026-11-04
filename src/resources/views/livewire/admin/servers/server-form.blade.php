<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar servidor' : 'Nuevo servidor' }}
    </h1>
    <p class="mt-1 text-sm text-slate-500">Gestiona los datos de conexión y el aprovisionamiento de cuentas de este servidor de hosting.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">{{ session('error') }}</div>
    @endif

    <form wire:submit="save" class="mt-6 space-y-6">
        {{-- ===================== Detalles del servidor ===================== --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Detalles del servidor</h2>
            <p class="mt-1 text-xs text-slate-400">Identificación básica y datos de red del servidor.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nombre</label>
                    <input type="text" wire:model="name"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nombre del host</label>
                    <input type="text" wire:model="hostname" placeholder="host1.dominio.com"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('hostname') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">IP</label>
                    <input type="text" wire:model="ip_address" placeholder="Ej. 127.0.0.1"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('ip_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Activar / Desactivar</label>
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="is_active"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Servidor activo
                    </label>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Notas</label>
                <textarea wire:model="notes" rows="2"
                          class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- =================== Configuración de conexión =================== --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Configuración de conexión</h2>
            <p class="mt-1 text-xs text-slate-400">Elige el gestor del panel y las credenciales para la comunicación con su API. Las credenciales guardadas no se muestran de nuevo; escribe un valor nuevo para reemplazarlas.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Gestor del servidor</label>
                    <select wire:model.live="panel"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Sin conexión (solo registro)</option>
                        @foreach ($panelOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('panel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($panel !== '')
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Conexión</label>
                        <select wire:model="panel_scheme"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="https">HTTPS (seguro)</option>
                            <option value="http">HTTP</option>
                        </select>
                        @error('panel_scheme') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Host de conexión</label>
                        <input type="text" wire:model="panel_host" placeholder="servidor.dominio.com o IP"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('panel_host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Puerto</label>
                        <input type="number" wire:model="panel_port" placeholder="{{ $panel === 'plesk' ? '8443' : '2087' }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('panel_port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-sm font-medium text-slate-700">
                            Usuario
                            @if ($this->isEditing() && $server->panel_user)
                                <span class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700">Configurado</span>
                            @endif
                        </label>
                        <input type="text" wire:model="panel_user" placeholder="{{ $panel === 'plesk' ? 'admin' : 'root' }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('panel_user') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-1 text-sm font-medium text-slate-700">
                            Token de API {{ $panel === 'whm' ? '(Access Hash / API Token)' : '(API Key)' }}
                            @if ($this->isEditing() && $server->panel_token)
                                <span class="rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700">Configurado</span>
                            @endif
                        </label>
                        <input type="password" wire:model="panel_token" autocomplete="new-password"
                               placeholder="{{ $this->isEditing() && $server->panel_token ? 'Déjalo vacío para conservar el actual' : 'Token de API' }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-400">Se guarda cifrado. Déjalo vacío para conservar el token actual.</p>
                        @error('panel_token') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Verificar certificado TLS</label>
                        <label class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" wire:model="panel_verify_tls"
                                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Verificar TLS
                        </label>
                        <p class="mt-1 text-xs text-slate-400">Desactívalo solo si el servidor usa un certificado autofirmado.</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- =================== Configuración opcional =================== --}}
        @if ($panel !== '')
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="font-semibold text-slate-900">Configuración opcional</h2>
                <p class="mt-1 text-xs text-slate-400">Valores usados al aprovisionar cuentas en este servidor.</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Prefijo de usuarios</label>
                        <input type="text" wire:model="panel_user_prefix" placeholder="Ej. wtv_"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-400">Prefijo para los usuarios y bases de datos creados.</p>
                        @error('panel_user_prefix') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Longitud de contraseña</label>
                        <input type="number" wire:model="panel_password_length" min="8" max="64"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-slate-400">Longitud de las contraseñas generadas al crear cuentas.</p>
                        @error('panel_password_length') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- ===================== Nombres de servidores ===================== --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="font-semibold text-slate-900">Nombres de servidores (nameservers)</h2>
            <p class="mt-1 text-xs text-slate-400">Nameservers que se sugerirán para los dominios aprovisionados en este servidor.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nameserver 1</label>
                    <input type="text" wire:model="ns1" placeholder="ns1.tudominio.com"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('ns1') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nameserver 2</label>
                    <input type="text" wire:model="ns2" placeholder="ns2.tudominio.com"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('ns2') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nameserver 3</label>
                    <input type="text" wire:model="ns3" placeholder="ns3.tudominio.com"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('ns3') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nameserver 4</label>
                    <input type="text" wire:model="ns4" placeholder="ns4.tudominio.com"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('ns4') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Resultado de "Guardar y probar conexión", junto a los botones. --}}
        @if ($testStatus !== '')
            <div class="rounded-md px-4 py-3 text-sm ring-1 {{ $testOk === true ? 'bg-green-50 text-green-700 ring-green-600/10' : ($testOk === false ? 'bg-red-50 text-red-700 ring-red-600/10' : 'bg-amber-50 text-amber-700 ring-amber-600/10') }}">
                <span class="font-medium">{{ $testOk === true ? '✓ Conexión:' : ($testOk === false ? '✗ Conexión:' : 'ℹ' ) }}</span>
                {{ $testStatus }}
            </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.servers.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
            @if ($panel !== '')
                <button type="button" wire:click="saveAndTest" wire:loading.attr="disabled" wire:target="saveAndTest"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveAndTest">Guardar y probar conexión</span>
                    <span wire:loading wire:target="saveAndTest">Probando…</span>
                </button>
            @endif
            <button type="submit"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear servidor' }}
            </button>
        </div>
    </form>
</div>
