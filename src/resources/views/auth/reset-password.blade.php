@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <h1 class="mb-6 text-center text-xl font-semibold text-slate-900">Restablecer contraseña</h1>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Nueva contraseña</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Restablecer contraseña
        </button>
    </form>
@endsection
