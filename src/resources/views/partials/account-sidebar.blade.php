{{-- Menú lateral del apartado "Mi Cuenta" del cliente.
     Espera: $active (slug de la sección activa). --}}
@php
    $isSubUser = auth()->user()?->isSubUser();
    $items = [
        ['route' => 'client.account.profile', 'slug' => 'profile', 'label' => 'Mis Datos'],
    ];
    if (! $isSubUser) {
        $items[] = ['route' => 'client.account.contacts', 'slug' => 'contacts', 'label' => 'Contactos/Sub-Cuentas'];
    }
    $items[] = ['route' => 'client.account.password', 'slug' => 'password', 'label' => 'Cambiar Contraseña'];
    $items[] = ['route' => 'client.account.security', 'slug' => 'security', 'label' => 'Ajustes de Seguridad'];
    $items[] = ['route' => 'client.account.emails', 'slug' => 'emails', 'label' => 'Emails Recibidos'];
@endphp

<aside class="w-full shrink-0 md:w-64">
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center gap-2 bg-indigo-600 px-4 py-3 text-white">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
            <span class="font-semibold">Mi Cuenta</span>
        </div>
        <nav class="divide-y divide-slate-100">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                   class="block px-4 py-3 text-sm {{ ($active ?? '') === $item['slug'] ? 'bg-indigo-50 font-medium text-indigo-700' : 'text-slate-600 hover:bg-slate-50' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</aside>
