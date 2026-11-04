@extends('layouts.guest')

@section('title', 'Confirma tu contraseña')

@section('content')
    <h1 class="mb-2 text-center text-xl font-semibold text-slate-900">Confirma tu contraseña</h1>
    <p class="mb-6 text-center text-sm text-slate-500">
        Por seguridad, confirma tu contraseña para continuar.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Confirmar
        </button>
    </form>
@endsection
