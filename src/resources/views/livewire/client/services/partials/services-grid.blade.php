{{-- Rejilla de tarjetas de servicios. Espera: $services (Collection<Service>). --}}
<div class="grid gap-4 md:grid-cols-2">
    @forelse ($services as $service)
        @php
            $days = $service->expires_at ? (int) now()->startOfDay()->diffInDays($service->expires_at->copy()->startOfDay(), false) : null;
            $expiringSoon = $days !== null && $days >= 0 && $days <= 30;
        @endphp
        <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-200" wire:key="svc-{{ $service->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="truncate font-semibold text-slate-900">{{ $service->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $service->type?->label() }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $service->status?->badgeClasses() }}">
                    {{ $service->status?->label() }}
                </span>
            </div>

            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Ciclo</dt>
                    <dd class="mt-0.5 text-slate-800">{{ $service->billing_cycle?->label() ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Precio</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $service->total()->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Vence</dt>
                    <dd class="mt-0.5 {{ $expiringSoon ? 'font-medium text-amber-600' : 'text-slate-800' }}">
                        {{ $service->expires_at?->translatedFormat('d M Y') ?: '—' }}
                        @if ($expiringSoon)
                            <span class="block text-xs font-normal text-amber-500">{{ $days === 0 ? 'Vence hoy' : 'En '.$days.' '.($days === 1 ? 'día' : 'días') }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($service->addons->isNotEmpty())
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Complementos</h3>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($service->addons as $addon)
                            <li class="flex items-center justify-between gap-3">
                                <span class="truncate text-slate-700">{{ $addon->name }}</span>
                                <span class="shrink-0 text-slate-500">{{ $addon->price()->format() }} · {{ $addon->status?->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 border-t border-slate-100 pt-4">
                <a href="{{ route('client.services.show', $service) }}"
                   class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:underline">
                    Ver detalle
                    @if ($service->type === \App\Domain\Services\Enums\ServiceType::Hosting)
                        y correos
                    @endif
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </a>
            </div>
        </div>
    @empty
        <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-900/5 md:col-span-2">
            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3"/></svg>
            <p class="mt-3 text-sm text-slate-500">Aún no tienes servicios contratados.</p>
        </div>
    @endforelse
</div>
