<div>
    @include('partials.client-page-header', [
        'title' => 'Base de conocimientos',
        'subtitle' => 'Guías y artículos de ayuda de WORTEV.',
    ])

    <div class="relative mt-5 max-w-md">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </span>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar artículos..."
               class="w-full rounded-lg border-slate-300 pl-9 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    @if ($total === 0)
        <div class="mt-6 rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-900/5">
            <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
            <p class="mt-3 text-sm text-slate-500">{{ $search !== '' ? 'No se encontraron artículos para tu búsqueda.' : 'Aún no hay artículos publicados.' }}</p>
        </div>
    @else
        <div class="mt-6 space-y-8">
            @foreach ($grouped as $categoryName => $articles)
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ $categoryName }}</h2>
                    <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                        @foreach ($articles as $article)
                            <a href="{{ route('client.kb.show', $article->slug) }}"
                               class="group flex items-center justify-between gap-3 px-5 py-4 transition hover:bg-slate-50" wire:key="kb-{{ $article->id }}">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900 group-hover:text-indigo-600">{{ $article->title }}</div>
                                        @if ($article->excerpt)
                                            <div class="mt-0.5 line-clamp-1 text-sm text-slate-500">{{ $article->excerpt }}</div>
                                        @endif
                                    </div>
                                </div>
                                <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
