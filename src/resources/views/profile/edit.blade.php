@extends('layouts.app', ['area' => auth()->user()->isAdminArea() ? 'admin' : 'client'])

@section('title', 'Mi perfil')

@section('content')
    <div class="mx-auto max-w-2xl space-y-8">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Mi perfil</h1>
            <p class="mt-1 text-slate-600">Actualiza tu información personal y tu contraseña.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-600/10">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Información del perfil --}}
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-medium text-slate-900">Información personal</h2>

            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700">Nombre</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-slate-400">Este es el correo con el que inicias sesión.</p>
                </div>

                <div>
                    <label for="secondary_email" class="block text-sm font-medium text-slate-700">Correo secundario (recuperación)</label>
                    <input id="secondary_email" name="secondary_email" type="email" value="{{ old('secondary_email', $user->secondary_email) }}"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-slate-400">Opcional. Si pierdes acceso a tu correo principal, podrás recibir aquí el enlace para restablecer tu contraseña.</p>
                </div>

                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                    Guardar cambios
                </button>
            </form>
        </section>

        {{-- Cambio de contraseña --}}
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <h2 class="text-lg font-medium text-slate-900">Cambiar contraseña</h2>

            <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium text-slate-700">Contraseña actual</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Nueva contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmar nueva contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                    Actualizar contraseña
                </button>
            </form>
        </section>

        {{-- Autenticación en dos pasos (2FA) --}}
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
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
@endsection
