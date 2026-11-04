<div>
    @if (session('status'))
        <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    @if ($step === 'number')
        <h1 class="text-lg font-semibold text-slate-900">Iniciar sesión</h1>
        <p class="mt-1 text-sm text-slate-500">Introduce tu número de empleado. Te enviaremos un código de acceso a tu correo.</p>

        <form wire:submit="requestCode" class="mt-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Número de empleado</label>
                <input type="text" wire:model="employee_number" autofocus
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('employee_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="requestCode"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="requestCode">Enviar código</span>
                <span wire:loading wire:target="requestCode">Enviando…</span>
            </button>
        </form>
    @else
        <h1 class="text-lg font-semibold text-slate-900">Introduce tu código</h1>
        <p class="mt-1 text-sm text-slate-500">Revisa tu correo. El código caduca en {{ \App\Domain\Internal\Services\EmployeeLoginService::TTL_MINUTES }} minutos.</p>

        <form wire:submit="verify" class="mt-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Código de acceso</label>
                <input type="text" wire:model="pin" inputmode="numeric" autocomplete="one-time-code" autofocus
                       class="mt-1 block w-full rounded-md border-slate-300 text-center text-lg tracking-widest shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('pin') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="verify"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="verify">Entrar</span>
                <span wire:loading wire:target="verify">Verificando…</span>
            </button>
            <button type="button" wire:click="restart"
                    class="w-full text-center text-sm text-slate-500 hover:text-slate-700 hover:underline">
                Usar otro número / reenviar código
            </button>
        </form>
    @endif
</div>
