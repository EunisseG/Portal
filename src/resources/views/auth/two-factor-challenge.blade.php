@extends('layouts.guest')

@section('title', 'Verificación en dos pasos')

@section('content')
    <h1 class="mb-2 text-center text-xl font-semibold text-slate-900">Verificación en dos pasos</h1>
    <p class="mb-6 text-center text-sm text-slate-500">
        Ingresa el código de tu app de autenticación.
    </p>

    {{-- Código del authenticator --}}
    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Código de autenticación</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
            Verificar
        </button>
    </form>

    {{-- Alternativa: código de recuperación (sin depender de JavaScript) --}}
    <details class="mt-6">
        <summary class="cursor-pointer text-center text-sm text-indigo-600 hover:underline">
            No tengo acceso a mi app · usar código de recuperación
        </summary>
        <form method="POST" action="{{ route('two-factor.login') }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="recovery_code" class="block text-sm font-medium text-slate-700">Código de recuperación</label>
                <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <button type="submit" class="w-full rounded-md bg-slate-700 px-4 py-2 font-medium text-white hover:bg-slate-800">
                Verificar con código de recuperación
            </button>
        </form>
    </details>
@endsection
