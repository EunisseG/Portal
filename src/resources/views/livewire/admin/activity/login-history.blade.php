<div>
    <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
    <p class="mt-1 text-slate-600">{{ $subtitle }}</p>

    <x-admin.search-input class="mt-4" placeholder="Buscar por nombre o correo" debounce="400" />

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logins as $login)
                    <tr wire:key="login-{{ $login->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $login->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $login->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $login->user?->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $login->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay inicios de sesión registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logins->links() }}
    </div>
</div>
