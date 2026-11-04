@extends('layouts.guest')

@section('title', 'Verifica tu correo')

@section('content')
    <h1 class="mb-2 text-center text-xl font-semibold text-slate-900">Verifica tu correo</h1>
    <p class="mb-6 text-center text-sm text-slate-600">
        Antes de continuar, revisa tu correo y haz clic en el enlace de verificación que te enviamos.
        Si no lo recibiste, podemos enviarte otro.
    </p>

    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full rounded-md bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700">
                Reenviar correo de verificación
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-md border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-50">
                Cerrar sesión
            </button>
        </form>
    </div>
@endsection
