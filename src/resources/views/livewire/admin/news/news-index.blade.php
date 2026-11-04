<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Noticias y anuncios</h1>
        @can('create', App\Domain\Content\Models\News::class)
            <a href="{{ route('admin.news.create') }}"
               class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Nueva noticia
            </a>
        @endcan
    </div>

    <x-admin.search-input class="mt-4" placeholder="Buscar por título..." />

    <div class="mt-4 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Título</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Publicación</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    <tr wire:key="news-{{ $item->id }}" class="transition hover:bg-slate-50/70">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $item->title }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $item->status?->badgeClasses() }}">
                                {{ $item->status?->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $item->published_at?->format('Y-m-d H:i') ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', App\Domain\Content\Models\News::class)
                                    <a href="{{ route('admin.news.edit', $item) }}" class="text-slate-600 hover:text-slate-900 hover:underline">Editar</a>
                                @endcan
                                @can('delete', App\Domain\Content\Models\News::class)
                                    <button type="button" wire:click="delete({{ $item->id }})"
                                            wire:confirm="¿Eliminar esta noticia?"
                                            class="text-red-600 hover:underline">Eliminar</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No hay noticias.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
