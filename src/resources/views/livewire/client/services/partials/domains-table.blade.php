{{-- Tabla de dominios. Espera: $domains (Collection<Domain>). --}}
<div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Dominio</th>
                    <th class="px-5 py-3">Servicio</th>
                    <th class="px-5 py-3">Estado</th>
                    <th class="px-5 py-3">Vence</th>
                    <th class="px-5 py-3">Renovación</th>
                    <th class="px-5 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($domains as $domain)
                    <tr wire:key="dom-{{ $domain->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center gap-2 font-medium text-slate-900">
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488M12 21a8.949 8.949 0 0 1-4.951-1.488M3.6 9h16.8M3.6 15h16.8M12 3a8.968 8.968 0 0 0-3.6 7.5M12 3a8.968 8.968 0 0 1 3.6 7.5"/></svg>
                                {{ $domain->name }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $domain->service?->name ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $domain->status?->badgeClasses() }}">
                                {{ $domain->status?->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">{{ $domain->expires_at?->translatedFormat('d M Y') ?: '—' }}</td>
                        <td class="px-5 py-3.5">
                            @if ($domain->auto_renew)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                    Automática
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">Manual</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button type="button" wire:click="startRenew({{ $domain->id }})"
                                    class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100">
                                Renovar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12">
                            <div class="flex flex-col items-center text-center">
                                <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488M12 21a8.949 8.949 0 0 1-4.951-1.488M3.6 9h16.8M3.6 15h16.8"/></svg>
                                <p class="mt-3 text-sm text-slate-500">Aún no tienes dominios registrados.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
