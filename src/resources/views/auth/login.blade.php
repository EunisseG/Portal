@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="mb-6 text-center text-xl font-semibold text-slate-900">Iniciar sesión</h1>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                Recordarme
            </label>

            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">
                ¿Olvidaste tu contraseña?
            </a>
        </div>

        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Entrar
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        ¿No tienes cuenta?
        <a href="{{ route('register') }}" class="text-indigo-600 hover:underline">Regístrate</a>
    </p>
@endsection
