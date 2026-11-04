<div class="md:flex md:gap-6">
    @include('partials.account-sidebar', ['active' => 'password'])

    <div class="mt-6 min-w-0 flex-1 md:mt-0">
        <h1 class="text-2xl font-semibold text-slate-900">Cambiar Contraseña</h1>

        <div class="mt-3 flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-slate-500 shadow-sm ring-1 ring-slate-900/5">
            <span>Área del Cliente</span>
            <span class="text-slate-300">›</span>
            <span class="font-medium text-indigo-600">Cambiar Contraseña</span>
        </div>

        @if (session('status') === 'password-updated')
            <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">Tu contraseña se actualizó correctamente.</div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium text-slate-700">Contraseña actual</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                       class="mt-1 block w-full max-w-md rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">Nueva contraseña</label>
                <input id="password" name="password" type="password" autocomplete="new-password"
                       class="mt-1 block w-full max-w-md rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmar nueva contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                       class="mt-1 block w-full max-w-md rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Actualizar contraseña
            </button>
        </form>
    </div>
</div>
