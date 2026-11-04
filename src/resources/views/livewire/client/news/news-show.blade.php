<div class="mx-auto max-w-3xl">
    <a href="{{ route('client.news.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a noticias</a>

    <article class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        @if ($news->coverUrl())
            <img src="{{ $news->coverUrl() }}" alt="" class="h-64 w-full object-cover">
        @endif
        <div class="p-8">
            <div class="text-xs text-slate-400">{{ $news->published_at?->format('d M Y H:i') }}</div>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $news->title }}</h1>
            @if ($news->excerpt)
                <p class="mt-2 text-lg text-slate-600">{{ $news->excerpt }}</p>
            @endif

            <div class="content-body mt-6 text-slate-700">
                {!! $news->bodyHtml() !!}
            </div>
        </div>
    </article>
</div>
