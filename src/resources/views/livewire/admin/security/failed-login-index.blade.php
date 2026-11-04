<div>
    <h1 class="text-2xl font-semibold text-slate-900">Intentos de inicio de sesión fallidos</h1>
    <p class="mt-1 text-slate-600">Registro de accesos rechazados por credenciales inválidas.</p>

    <x-admin.search-input class="mt-4" placeholder="Buscar por correo" debounce="400" />

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Correo intentado</th>
                    <th class="px-4 py-3">IP</th>
                    <th class="px-4 py-3">Navegador</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($attempts as $attempt)
                    <tr wire:key="attempt-{{ $attempt->id }}">
                        <td class="px-4 py-3 text-slate-600">{{ $attempt->attempted_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-700">{{ $attempt->email }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $attempt->ip_address ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Str::limit($attempt->user_agent, 60) ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay intentos fallidos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $attempts->links() }}
    </div>
</div>
