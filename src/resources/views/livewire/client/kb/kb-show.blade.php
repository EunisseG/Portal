<div class="mx-auto max-w-3xl">
    <a href="{{ route('client.kb.index') }}" class="text-sm text-slate-600 hover:underline">← Volver a la base de conocimientos</a>

    <article class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        @if ($article->coverUrl())
            <img src="{{ $article->coverUrl() }}" alt="" class="h-56 w-full object-cover">
        @endif
        <div class="p-8">
            <div class="text-xs font-medium uppercase tracking-wide text-indigo-600">{{ $article->category?->name }}</div>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $article->title }}</h1>

            <div class="content-body mt-6 text-slate-700">
                {!! $article->bodyHtml() !!}
            </div>
        </div>
    </article>
</div>
