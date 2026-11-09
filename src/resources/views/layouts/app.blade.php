@php
    /** @var string $area  'client' o 'admin' */
    $area = $area ?? 'client';
    $isAdmin = $area === 'admin';
    $accent = $isAdmin ? 'bg-slate-900' : 'bg-indigo-600';
    $areaLabel = $isAdmin ? 'Administración' : 'Portal cliente';

    // Marca configurable (nombre, logo, favicon).
    $branding = app(\App\Support\Branding::class);
    $brandName = $branding->name();
    $brandLogo = $branding->logoUrl();
    $brandFavicon = $branding->faviconUrl();

    /*
     | Navegación admin organizada en grupos con submenús. Cada grupo tiene un
     | patrón para marcarse activo y una lista de ítems. Los ítems marcados con
     | 'disabled' => true son módulos aún no construidos (placeholders).
     */
    $adminGroups = [
        [
            'label' => 'Dashboard',
            'pattern' => 'admin',
            'route' => 'admin.dashboard',
            'items' => [],
        ],
        [
            'label' => 'Usuarios',
            'pattern' => 'admin/clientes*|admin/ejecutivos*|admin/empleados*',
            'items' => [
                ['route' => 'admin.clients.index', 'label' => 'Clientes', 'pattern' => 'admin/clientes*'],
                ['route' => 'admin.employees.index', 'label' => 'Empleados', 'pattern' => 'admin/empleados*'],
                ['route' => 'admin.sales-reps.index', 'label' => 'Ejecutivos de ventas', 'pattern' => 'admin/ejecutivos*'],
            ],
        ],
        [
            'label' => 'Pagos',
            'pattern' => 'admin/facturas*|admin/ordenes-pago*|admin/monedas*',
            'items' => [
                ['route' => 'admin.invoices.index', 'label' => 'Facturas', 'pattern' => 'admin/facturas*'],
                ['route' => 'admin.payment-orders.index', 'label' => 'Órdenes de pago', 'pattern' => 'admin/ordenes-pago*'],
                ['route' => 'admin.currencies.index', 'label' => 'Tipos de moneda', 'pattern' => 'admin/monedas*'],
            ],
        ],
        [
            'label' => 'Pedidos',
            'pattern' => 'admin/servicios*',
            'route' => 'admin.services.index',
            'items' => [],
        ],
        [
            'label' => 'Productos',
            'pattern' => 'admin/productos*|admin/complementos*|admin/catalogo-complementos*|admin/promociones*',
            'items' => [
                ['route' => 'admin.products.index', 'label' => 'Productos / Servicios', 'pattern' => 'admin/productos*'],
                ['route' => 'admin.addon-catalog.index', 'label' => 'Complementos', 'pattern' => 'admin/catalogo-complementos*|admin/complementos*'],
                ['route' => 'admin.promotions.index', 'label' => 'Promociones de productos', 'pattern' => 'admin/promociones*'],
            ],
        ],
        [
            'label' => 'Soporte',
            'pattern' => 'admin/tickets*|admin/tickets-internos*|admin/respuestas*|admin/kb*|admin/vencimientos*|admin/satisfaccion*|admin/sla*',
            'items' => [
                ['route' => 'admin.tickets.index', 'label' => 'Tickets de clientes', 'pattern' => 'admin/tickets|admin/tickets/*'],
                ['route' => 'admin.internal-tickets.index', 'label' => 'Tickets internos', 'pattern' => 'admin/tickets-internos*'],
                ['route' => 'admin.sla.index', 'label' => 'SLA / Soporte', 'pattern' => 'admin/sla*'],
                ['route' => 'admin.canned.index', 'label' => 'Respuestas preparadas', 'pattern' => 'admin/respuestas-preparadas*'],
                ['route' => 'admin.kb.articles.index', 'label' => 'Base de conocimiento', 'pattern' => 'admin/kb*'],
                ['route' => 'admin.renewals.index', 'label' => 'Vencimientos', 'pattern' => 'admin/vencimientos*'],
                ['route' => 'admin.satisfaction.index', 'label' => 'Satisfacción', 'pattern' => 'admin/satisfaccion*'],
            ],
        ],
        [
            'label' => 'Sistema',
            'pattern' => 'admin/graficas*|admin/planes*|admin/servidores*|admin/configuracion*|admin/integraciones*|admin/areas-internas*|admin/encuestas*',
            'items' => [
                ['route' => 'admin.reports.index', 'label' => 'Gráficas', 'pattern' => 'admin/graficas*'],
                ['route' => 'admin.servers.index', 'label' => 'Servidores / Planes', 'pattern' => 'admin/servidores*|admin/planes*'],
                ['route' => 'admin.internal-areas.index', 'label' => 'Áreas internas', 'pattern' => 'admin/areas-internas*'],
                ['route' => 'admin.surveys.index', 'label' => 'Encuestas', 'pattern' => 'admin/encuestas*'],
                ['route' => 'admin.settings.branding', 'label' => 'Apariencia', 'pattern' => 'admin/configuracion/apariencia*'],
                ['route' => 'admin.settings.operation', 'label' => 'Modo de operación', 'pattern' => 'admin/configuracion/operacion*'],
                ['route' => 'admin.integrations.index', 'label' => 'Integraciones', 'pattern' => 'admin/integraciones|admin/integraciones/catalogo*'],
                ['route' => 'admin.integrations.logs', 'label' => 'Registros de integración', 'pattern' => 'admin/integraciones/logs*'],
            ],
        ],
        [
            'label' => 'Seguridad',
            'pattern' => 'admin/usuarios*|admin/auditoria*|admin/seguridad*|admin/roles*|admin/sistema/dependencias*',
            'items' => [
                ['route' => 'admin.users.index', 'label' => 'Usuarios', 'pattern' => 'admin/usuarios*'],
                ['route' => 'admin.roles.index', 'label' => 'Roles y permisos', 'pattern' => 'admin/roles*'],
                ['route' => 'admin.audit.index', 'label' => 'Auditoría', 'pattern' => 'admin/auditoria*'],
                ['route' => 'admin.security.failed-logins', 'label' => 'Accesos fallidos', 'pattern' => 'admin/seguridad*'],
                ['route' => 'admin.system.dependencies', 'label' => 'Dependencias / Seguridad', 'pattern' => 'admin/sistema/dependencias*'],
            ],
        ],
        [
            'label' => 'Actividad',
            'pattern' => 'admin/actividad*',
            'items' => [
                ['route' => 'admin.activity.staff-logins', 'label' => 'Inicio de sesión del personal', 'pattern' => 'admin/actividad/accesos-personal*'],
                ['route' => 'admin.activity.client-logins', 'label' => 'Inicio de sesión del cliente', 'pattern' => 'admin/actividad/accesos-cliente*'],
                ['route' => 'admin.activity.emails', 'label' => 'Correos enviados', 'pattern' => 'admin/actividad/correos*'],
            ],
        ],
        [
            'label' => 'Anuncios',
            'pattern' => 'admin/noticias*|admin/mantenimientos*',
            'items' => [
                ['route' => 'admin.news.index', 'label' => 'Noticias', 'pattern' => 'admin/noticias*'],
                ['route' => 'admin.maintenances.index', 'label' => 'Mantenimientos', 'pattern' => 'admin/mantenimientos*'],
            ],
        ],
    ];

    $clientNav = [
        ['route' => 'client.dashboard', 'label' => 'Inicio', 'pattern' => 'client'],
        ['route' => 'client.services.index', 'label' => 'Servicios', 'pattern' => 'client/servicios*|client/dominios*'],
        [
            // Facturas (CFDI firmadas por el SAT) y, aparte, las órdenes de
            // pago con sus comprobantes.
            'label' => 'Pagos',
            'pattern' => 'client/facturas*|client/comprobantes*|client/ordenes-pago*',
            'items' => [
                ['route' => 'client.invoices.index', 'label' => 'Facturas', 'pattern' => 'client/facturas*'],
                ['route' => 'client.proofs.index', 'label' => 'Comprobantes de pago', 'pattern' => 'client/comprobantes*|client/ordenes-pago*'],
            ],
        ],
        ['route' => 'client.tickets.index', 'label' => 'Tickets', 'pattern' => 'client/tickets*'],
        ['route' => 'client.news.index', 'label' => 'Noticias', 'pattern' => 'client/noticias*'],
        ['route' => 'client.maintenances.index', 'label' => 'Mantenimientos', 'pattern' => 'client/mantenimientos*'],
        ['route' => 'client.kb.index', 'label' => 'Base de conocimiento', 'pattern' => 'client/kb*'],
    ];

    // "Mi Cuenta" (datos, contactos, contraseña, seguridad, correos) para clientes.
    if (! $isAdmin && auth()->user()) {
        $clientNav[] = ['route' => 'client.account.profile', 'label' => 'Mi Cuenta', 'pattern' => 'client/mi-cuenta*'];
    }

    /** Marca un grupo/ítem como activo según patrones separados por '|'. */
    $isActive = fn (?string $pattern) => $pattern && request()->is(explode('|', $pattern));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $brandName) · {{ $areaLabel }}</title>
    @if ($brandFavicon)
        <link rel="icon" href="{{ $brandFavicon }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important;}</style>
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-screen">
        <header class="{{ $accent }} text-white">
            {{-- Barra superior: logo + sesión --}}
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-3">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-8 w-auto object-contain">
                    @else
                        <span class="text-lg font-bold tracking-tight">{{ $brandName }}</span>
                    @endif
                    <span class="rounded bg-white/15 px-2 py-0.5 text-xs font-medium uppercase tracking-wide">
                        {{ $areaLabel }}
                    </span>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    @if (! $isAdmin)
                        @livewire('client.notification-bell')
                    @endif
                    <a href="{{ route('profile.edit') }}" class="hover:underline">
                        {{ auth()->user()?->name }}
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded bg-white/15 px-3 py-1.5 font-medium hover:bg-white/25">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>

            {{-- Barra de navegación --}}
            <nav class="border-t border-white/10 bg-black/10">
                <div class="mx-auto flex max-w-7xl flex-wrap items-stretch gap-1 px-2">
                    @if ($isAdmin)
                        @foreach ($adminGroups as $group)
                            @php $groupActive = $isActive($group['pattern']); @endphp
                            @if (empty($group['items']))
                                {{-- Grupo sin submenú: enlace directo --}}
                                <a href="{{ route($group['route']) }}" wire:navigate
                                   class="flex items-center px-3 py-2.5 text-sm font-medium {{ $groupActive ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                    {{ $group['label'] }}
                                </a>
                            @else
                                {{-- Grupo con submenú desplegable (hover) --}}
                                <div class="group relative">
                                    <button type="button"
                                            class="flex items-center gap-1 px-3 py-2.5 text-sm font-medium {{ $groupActive ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                        {{ $group['label'] }}
                                        <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                    <div class="invisible absolute left-0 top-full z-20 min-w-56 rounded-b-lg bg-white py-1 text-slate-700 opacity-0 shadow-lg ring-1 ring-slate-900/5 transition group-hover:visible group-hover:opacity-100">
                                        @foreach ($group['items'] as $item)
                                            @if (! empty($item['disabled']))
                                                <span class="flex cursor-not-allowed items-center justify-between px-4 py-2 text-sm text-slate-400"
                                                      title="Próximamente">
                                                    {{ $item['label'] }}
                                                    <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-400">Pronto</span>
                                                </span>
                                            @else
                                                @php $itemActive = $isActive($item['pattern'] ?? null); @endphp
                                                <a href="{{ route($item['route']) }}" wire:navigate
                                                   class="block px-4 py-2 text-sm {{ $itemActive ? 'bg-indigo-50 font-medium text-indigo-700' : 'hover:bg-slate-50' }}">
                                                    {{ $item['label'] }}
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @else
                        @foreach ($clientNav as $item)
                            @php $active = request()->is(explode('|', $item['pattern'])); @endphp
                            @if (empty($item['items']))
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   class="flex items-center px-3 py-2.5 text-sm font-medium {{ $active ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                    {{ $item['label'] }}
                                </a>
                            @else
                                {{-- Grupo con submenú desplegable (hover) --}}
                                <div class="group relative">
                                    <button type="button"
                                            class="flex items-center gap-1 px-3 py-2.5 text-sm font-medium {{ $active ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                                        {{ $item['label'] }}
                                        <svg class="h-3.5 w-3.5 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                    <div class="invisible absolute left-0 top-full z-20 min-w-56 rounded-b-lg bg-white py-1 text-slate-700 opacity-0 shadow-lg ring-1 ring-slate-900/5 transition group-hover:visible group-hover:opacity-100">
                                        @foreach ($item['items'] as $sub)
                                            @php $subActive = request()->is(explode('|', $sub['pattern'])); @endphp
                                            <a href="{{ route($sub['route']) }}" wire:navigate
                                               class="block px-4 py-2 text-sm {{ $subActive ? 'bg-indigo-50 font-medium text-indigo-700' : 'hover:bg-slate-50' }}">
                                                {{ $sub['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            </nav>
        </header>

        <div class="mx-auto max-w-7xl px-4 py-8">
            <main class="min-w-0">
                @if (session('status'))
                    <div class="mb-6 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                        {{ session('status') }}
                    </div>
                @endif

                {{-- Soporta tanto páginas Blade (@extends con @section('content'))
                     como páginas Livewire (que inyectan su HTML en $slot). --}}
                @if (isset($slot))
                    {{ $slot }}
                @else
                    @yield('content')
                @endif
            </main>
        </div>
    </div>
    @livewireScripts
    @stack('scripts')
</body>
</html>
