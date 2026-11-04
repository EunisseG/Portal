<div>
    @include('partials.client-page-header', [
        'title' => 'Mantenimientos programados',
        'subtitle' => 'Avisos sobre ventanas de mantenimiento que pueden afectar tus servicios.',
    ])

    <div class="mt-6 space-y-4">
        @forelse ($items as $item)
            <div wire:key="maint-{{ $item->id }}" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                <div class="flex gap-4 p-5">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-semibold text-slate-900">{{ $item->title }}</h2>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $item->status?->badgeClasses() }}">
                                {{ $item->status?->label() }}
                            </span>
                        </div>
                        <p class="mt-1 inline-flex items-center gap-1.5 text-sm text-slate-500">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            {{ $item->starts_at?->translatedFormat('d M Y · H:i') }}
                            @if ($item->ends_at) <span class="text-slate-400">→</span> {{ $item->ends_at->translatedFormat('d M Y · H:i') }} @endif
                        </p>
                        @if ($item->description)
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $item->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-900/5">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <p class="mt-3 text-sm text-slate-500">No hay mantenimientos programados por el momento.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
