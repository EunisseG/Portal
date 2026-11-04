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
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal de empleados · {{ $brandName }}</title>
    @if ($brandFavicon)
        <link rel="icon" href="{{ $brandFavicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="mb-6 text-center">
            @if ($brandLogo)
                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mx-auto h-12 w-auto object-contain">
            @else
                <span class="text-2xl font-bold tracking-tight text-slate-900">{{ $brandName }}</span>
            @endif
            <p class="mt-2 text-sm font-medium uppercase tracking-wide text-slate-400">Portal de empleados</p>
        </div>

        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-900/5">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-slate-400">Acceso exclusivo para personal de {{ $brandName }}.</p>
    </div>
    @livewireScripts
</body>
</html>
