@php
    $branding = app(\App\Support\Branding::class);
    $brandName = $branding->name();
    $brandLogo = $branding->logoUrl();
    $brandFavicon = $branding->faviconUrl();
    $me = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal de empleados') · {{ $brandName }}</title>
    @if ($brandFavicon)
        <link rel="icon" href="{{ $brandFavicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-screen">
        <header class="bg-slate-900 text-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-8 w-auto object-contain">
                    @else
                        <span class="text-lg font-bold tracking-tight">{{ $brandName }}</span>
                    @endif
                    <span class="rounded bg-white/15 px-2 py-0.5 text-xs font-medium uppercase tracking-wide">Empleados</span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-white/80">{{ $me?->name }}{{ $me?->internalArea ? ' · '.$me->internalArea->name : '' }}</span>
                    <form method="POST" action="{{ route('employee.logout') }}">
                        @csrf
                        <button type="submit" class="rounded bg-white/15 px-3 py-1.5 font-medium hover:bg-white/25">Cerrar sesión</button>
                    </form>
                </div>
            </div>
            <nav class="border-t border-white/10 bg-black/10">
                <div class="mx-auto flex max-w-5xl items-stretch gap-1 px-2">
                    <a href="{{ route('employee.dashboard') }}" wire:navigate
                       class="flex items-center px-3 py-2.5 text-sm font-medium {{ request()->routeIs('employee.dashboard') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        Mis tickets
                    </a>
                </div>
            </nav>
        </header>

        <div class="mx-auto max-w-5xl px-4 py-8">
            <main class="min-w-0">
                @if (session('status'))
                    <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                        {{ session('status') }}
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
