<div>
    @include('partials.client-page-header', [
        'title' => 'Noticias y anuncios',
        'subtitle' => 'Novedades de WORTEV.',
    ])

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($items as $item)
            <a href="{{ route('client.news.show', $item->slug) }}"
               class="group flex flex-col overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-indigo-500/40" wire:key="n-{{ $item->id }}">
                @if ($item->coverUrl())
                    <div class="h-40 w-full overflow-hidden">
                        <img src="{{ $item->coverUrl() }}" alt="" class="h-40 w-full object-cover transition duration-300 group-hover:scale-105">
                    </div>
                @else
                    <div class="flex h-40 w-full items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400">
                        <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                    </div>
                @endif
                <div class="flex flex-1 flex-col p-5">
                    <div class="text-xs font-medium text-indigo-500">{{ $item->published_at?->translatedFormat('d M Y') }}</div>
                    <h2 class="mt-1 font-semibold text-slate-900 group-hover:text-indigo-600">{{ $item->title }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm text-slate-600">{{ $item->excerpt }}</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-indigo-600">
                        Leer más
                        <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-900/5 sm:col-span-2 lg:col-span-3">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/></svg>
                <p class="mt-3 text-sm text-slate-500">Aún no hay noticias publicadas.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $items->links() }}</div>
</div>
