<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-semibold text-slate-900">
        {{ $this->isEditing() ? 'Editar artículo' : 'Nuevo artículo' }}
    </h1>

    <form wire:submit="save" class="mt-6 space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Categoría</label>
                <select wire:model="kb_category_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('kb_category_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Estado</label>
                <select wire:model="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Título</label>
            <input type="text" wire:model="title"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Resumen (opcional)</label>
            <textarea wire:model="excerpt" rows="2"
                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            @error('excerpt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Imagen de portada</label>
            @if ($this->isEditing() && $article->coverUrl())
                <img src="{{ $article->coverUrl() }}" alt="Portada actual" class="mt-2 h-32 rounded-md object-cover ring-1 ring-slate-900/5">
            @endif
            <input type="file" wire:model="cover" accept="image/*"
                   class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
            <div wire:loading wire:target="cover" class="mt-1 text-xs text-slate-400">Subiendo...</div>
            @error('cover') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Contenido (Markdown)</label>
            <textarea wire:model="body" rows="12"
                      class="mt-1 block w-full rounded-md border-slate-300 font-mono text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"></textarea>
            <p class="mt-1 text-xs text-slate-400">Usa Markdown: <code>## Título</code>, <code>**negrita**</code>, <code>![alt](url)</code> para imágenes.</p>
            @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-lg bg-slate-50 p-4">
            <label class="block text-sm font-medium text-slate-700">Imágenes para el contenido (opcional)</label>
            <input type="file" wire:model="gallery" multiple accept="image/*"
                   class="mt-2 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">
            <div wire:loading wire:target="gallery" class="mt-1 text-xs text-slate-400">Subiendo...</div>
            @error('gallery.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            @if ($this->isEditing() && $article->images->isNotEmpty())
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach ($article->images as $image)
                        <div class="rounded-md bg-white p-2 ring-1 ring-slate-900/5">
                            <img src="{{ $image->url() }}" alt="" class="h-20 w-full rounded object-cover">
                            <input type="text" readonly value="![]({{ $image->url() }})"
                                   class="mt-1 w-full rounded border-slate-200 bg-slate-50 text-xs text-slate-600"
                                   onclick="this.select()">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Fecha de publicación (opcional)</label>
            <input type="datetime-local" wire:model="published_at"
                   class="mt-1 block w-full max-w-xs rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @error('published_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50">
                {{ $this->isEditing() ? 'Guardar cambios' : 'Crear artículo' }}
            </button>
            <a href="{{ route('admin.kb.articles.index') }}" class="text-sm text-slate-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
