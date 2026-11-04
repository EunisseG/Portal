@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <h1 class="mb-2 text-center text-xl font-semibold text-slate-900">Recuperar contraseña</h1>
    <p class="mb-6 text-center text-sm text-slate-600">
        Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
    </p>

    <form method="POST" action="{{ route('password.email.recovery') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-500">Puedes usar tu correo principal o tu correo secundario de recuperación.</p>
        </div>

        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Enviar enlace
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Volver a iniciar sesión</a>
    </p>
@endsection
