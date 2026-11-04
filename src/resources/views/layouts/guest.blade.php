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
    <title>@yield('title', $brandName)</title>
    @if ($brandFavicon)
        <link rel="icon" href="{{ $brandFavicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="mb-6 text-center">
            <a href="{{ route('home') }}" class="inline-block text-2xl font-bold tracking-tight text-slate-900">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="mx-auto h-12 w-auto object-contain">
                @else
                    {{ $brandName }}
                @endif
            </a>
        </div>

        <div class="w-full max-w-md rounded-xl bg-white p-8 shadow-sm ring-1 ring-slate-900/5">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</body>
</html>
