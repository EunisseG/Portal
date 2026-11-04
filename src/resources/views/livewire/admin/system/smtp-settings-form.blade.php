<div class="mx-auto max-w-2xl">
    <a href="{{ route('admin.integrations.index') }}" class="text-sm text-slate-600 hover:underline">← Integraciones</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Configuración de correo (SMTP)</h1>
    <p class="mt-1 text-slate-600">Configura el servidor de correo para enviar las notificaciones del portal.</p>

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

    <div class="mt-4 rounded-md {{ $isConfigured ? 'bg-green-50 text-green-700 ring-green-600/10' : 'bg-amber-50 text-amber-700 ring-amber-600/10' }} px-4 py-2 text-sm ring-1">
        {{ $isConfigured ? 'SMTP configurado: los correos se envían por este servidor.' : 'SMTP no configurado: los correos quedan registrados sin enviarse (modo prueba).' }}
    </div>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Servidor (host)</label>
                <input type="text" wire:model="host" placeholder="ej. mailpit o smtp.tudominio.com"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('host') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Puerto</label>
                <input type="number" wire:model="port"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('port') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Cifrado</label>
                <select wire:model="encryption" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                    <option value="tls">TLS</option>
                    <option value="ssl">SSL</option>
                    <option value="none">Ninguno</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Usuario</label>
                <input type="text" wire:model="username" autocomplete="off"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('username') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Contraseña</label>
                <input type="password" wire:model="password" autocomplete="new-password"
                       placeholder="{{ $hasPassword ? '•••••• (sin cambios)' : '' }}"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-400">Déjalo vacío para conservar la contraseña actual.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Remitente (correo)</label>
                <input type="email" wire:model="from_address"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('from_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Remitente (nombre)</label>
                <input type="text" wire:model="from_name"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('from_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Guardar configuración
        </button>
    </form>

    {{-- Enviar correo de prueba --}}
    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Enviar correo de prueba</h2>
        <p class="mt-1 text-sm text-slate-500">Envía un correo usando la configuración actual para verificar que funciona.</p>
        <form wire:submit="sendTest" class="mt-3 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Destinatario</label>
                <input type="email" wire:model="testEmail" placeholder="tu@correo.com"
                       class="mt-1 block w-72 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('testEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="sendTest"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="sendTest">Enviar prueba</span>
                <span wire:loading wire:target="sendTest">Enviando…</span>
            </button>
        </form>

        {{-- Resultado del último envío, justo aquí para que sea visible. --}}
        @if ($testStatus !== '')
            <div class="mt-4 rounded-md px-4 py-3 text-sm ring-1 {{ $testOk ? 'bg-green-50 text-green-700 ring-green-600/10' : 'bg-red-50 text-red-700 ring-red-600/10' }}">
                <span class="font-medium">{{ $testOk ? '✓ Enviado:' : '✗ Error:' }}</span>
                {{ $testStatus }}
            </div>
        @endif
    </div>
</div>
