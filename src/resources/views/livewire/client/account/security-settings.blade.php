<div class="md:flex md:gap-6">
    @include('partials.account-sidebar', ['active' => 'security'])

    <div class="mt-6 min-w-0 flex-1 md:mt-0">
        <h1 class="text-2xl font-semibold text-slate-900">Ajustes de Seguridad</h1>

        <div class="mt-3 flex items-center gap-2 rounded-lg bg-white px-4 py-3 text-sm text-slate-500 shadow-sm ring-1 ring-slate-900/5">
            <span>Área del Cliente</span>
            <span class="text-slate-300">›</span>
            <span class="font-medium text-indigo-600">Ajustes de Seguridad</span>
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Autenticación en dos pasos (2FA) --}}
        <section class="mt-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-medium text-slate-900">Autenticación en dos pasos</h2>
            <p class="mt-1 text-sm text-slate-500">
                Añade una capa extra de seguridad: al iniciar sesión se te pedirá un código de tu app de autenticación (Google Authenticator, Authy, etc.).
            </p>

            @if (session('status') === 'two-factor-authentication-enabled')
                <div class="mt-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-600/10">
                    Escanea el código QR con tu app y confirma con un código para activar.
                </div>
            @elseif (session('status') === 'two-factor-authentication-confirmed')
                <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                    Autenticación en dos pasos activada.
                </div>
            @elseif (session('status') === 'two-factor-authentication-disabled')
                <div class="mt-4 rounded-md bg-slate-100 px-4 py-3 text-sm text-slate-600">
                    Autenticación en dos pasos desactivada.
                </div>
            @elseif (session('status') === 'recovery-codes-generated')
                <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                    Se generaron nuevos códigos de recuperación.
                </div>
            @endif

            @php $twoFactorEnabled = ! is_null($user->two_factor_secret); @endphp

            @if (! $twoFactorEnabled)
                {{-- No activado: botón para iniciar la activación --}}
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Activar autenticación en dos pasos
                    </button>
                </form>
            @else
                {{-- Activado o pendiente de confirmar --}}
                @if (is_null($user->two_factor_confirmed_at))
                    <div class="mt-4">
                        <p class="text-sm text-slate-600">1. Escanea este código QR con tu app de autenticación:</p>
                        <div class="mt-3 inline-block rounded-lg bg-white p-3 ring-1 ring-slate-200">
                            {!! $user->twoFactorQrCodeSvg() !!}
                        </div>

                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="tf_code" class="block text-sm font-medium text-slate-700">2. Ingresa el código generado</label>
                                <input id="tf_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                                       class="mt-1 block w-48 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                Confirmar y activar
                            </button>
                        </form>
                    </div>
                @else
                    <div class="mt-4 flex items-center gap-2">
                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Activado</span>
                    </div>
                @endif

                {{-- Códigos de recuperación --}}
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <h3 class="text-sm font-semibold text-slate-700">Códigos de recuperación</h3>
                    <p class="mt-1 text-xs text-slate-500">Guárdalos en un lugar seguro. Te permiten entrar si pierdes acceso a tu app.</p>
                    <div class="mt-3 grid max-w-sm grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-700">
                        @foreach ($user->recoveryCodes() as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Regenerar códigos
                            </button>
                        </form>
                        <form method="POST" action="{{ route('two-factor.disable') }}"
                              onsubmit="return confirm('¿Desactivar la autenticación en dos pasos?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100">
                                Desactivar
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
