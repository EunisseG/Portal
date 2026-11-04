@php
    $branding = app(\App\Support\Branding::class);
    $brandName = $branding->name();
    $brandLogo = $branding->logoUrl();
    $brandFavicon = $branding->faviconUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }}</title>
    @if ($brandFavicon)
        <link rel="icon" href="{{ $brandFavicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        @if ($brandLogo)
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mb-4 h-16 w-auto object-contain">
        @endif
        <h1 class="text-4xl font-bold tracking-tight text-slate-900">
            {{ $brandName }}
        </h1>
        <p class="mt-3 max-w-md text-slate-600">
            Consulta y administra tus servicios contratados y comunícate con WORTEV.
        </p>

        <div class="mt-8 flex gap-3">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="rounded-md bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-700">
                    Ir a mi panel
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="rounded-md bg-indigo-600 px-5 py-2.5 font-medium text-white hover:bg-indigo-700">
                    Iniciar sesión
                </a>
                <a href="{{ route('register') }}"
                   class="rounded-md border border-slate-300 bg-white px-5 py-2.5 font-medium text-slate-700 hover:bg-slate-50">
                    Crear cuenta
                </a>
            @endauth
        </div>
    </div>
</body>
</html>
