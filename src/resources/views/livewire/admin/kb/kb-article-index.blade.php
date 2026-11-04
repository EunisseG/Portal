<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Artículos de conocimiento</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.kb.categories.index') }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Categorías</a>
            @can('create', App\Domain\Content\Models\KbArticle::class)
                <a href="{{ route('admin.kb.articles.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Nuevo artículo</a>
            @endcan
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <x-admin.search-input placeholder="Buscar por título..." :hint="false" />
        <select wire:model.live="categoryFilter" class="rounded-md border-slate-300 text-sm shadow-sm">
            <option value="">Todas las categorías</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Título</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($articles as $article)
                    <tr wire:key="art-{{ $article->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $article->title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $article->category?->name }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $article->status?->badgeClasses() }}">
                                {{ $article->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', App\Domain\Content\Models\KbArticle::class)
                                    <a href="{{ route('admin.kb.articles.edit', $article) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                                @can('delete', App\Domain\Content\Models\KbArticle::class)
                                    <button type="button" wire:click="delete({{ $article->id }})"
                                            wire:confirm="¿Eliminar este artículo?"
                                            class="text-red-600 hover:underline">Eliminar</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay artículos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>
</div>
