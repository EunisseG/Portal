<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">Modo de operación</h1>
    <p class="mt-1 text-slate-600">Define si los pagos y la renovación de dominios se gestionan de forma manual o automática.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="mt-6 space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        {{-- Pagos --}}
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-6">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Pagos en línea</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <span class="font-medium">Manual:</span> el cliente sube su comprobante y el administrador lo aprueba.<br>
                    <span class="font-medium">Automático:</span> el cliente paga desde el portal con una pasarela configurada en Integraciones.
                </p>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="paymentsAutomatic" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        {{-- Renovación de dominios --}}
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Renovación de dominios</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <span class="font-medium">Manual:</span> el administrador renueva el dominio desde el panel.<br>
                    <span class="font-medium">Automático:</span> la renovación se realiza contra el proveedor de dominios configurado.
                </p>
            </div>
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" wire:model="domainRenewalAutomatic" class="peer sr-only">
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-5"></div>
            </label>
        </div>

        <div class="rounded-md bg-amber-50 px-4 py-3 text-xs text-amber-700 ring-1 ring-amber-600/10">
            El modo automático requiere tener el proveedor correspondiente activo y configurado en
            <a href="{{ route('admin.integrations.index') }}" class="font-medium underline">Integraciones</a>.
            Si no lo está, el flujo seguirá pidiendo la acción manual.
        </div>

        <div class="border-t border-slate-100 pt-6">
            <button type="submit"
                    wire:loading.attr="disabled" wire:target="save"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Guardar cambios</span>
                <span wire:loading wire:target="save">Guardando…</span>
            </button>
        </div>
    </form>
</div>
