<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold text-slate-900">Apariencia</h1>
    <p class="mt-1 text-slate-600">Personaliza el nombre, el logo y el favicon del portal.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="save" class="mt-6 space-y-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        {{-- Nombre del portal --}}
        <div>
            <label class="block text-sm font-medium text-slate-700">Nombre del portal</label>
            <input type="text" wire:model="name" placeholder="Portal WORTEV"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <p class="mt-1 text-xs text-slate-400">Aparece en la barra superior, los títulos y la pantalla de acceso.</p>
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Logo --}}
        <div class="border-t border-slate-100 pt-6">
            <label class="block text-sm font-medium text-slate-700">Logo</label>
            <p class="mt-0.5 text-xs text-slate-400">PNG, JPG, SVG o WEBP. Máx. 2 MB. Si lo defines, reemplaza al nombre en la barra superior.</p>

            <div class="mt-3 flex items-center gap-4">
                @if ($logo && method_exists($logo, 'isPreviewable') && $logo->isPreviewable())
                    <img src="{{ $logo->temporaryUrl() }}" alt="Vista previa del logo" class="h-12 w-auto rounded bg-slate-100 object-contain p-1 ring-1 ring-slate-200">
                @elseif ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo actual" class="h-12 w-auto rounded bg-slate-100 object-contain p-1 ring-1 ring-slate-200">
                @else
                    <span class="text-xs text-slate-400">Sin logo (se usa el nombre).</span>
                @endif

                <div class="flex items-center gap-2">
                    <input type="file" wire:model="logo" accept="image/*"
                           class="text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">
                    @if ($logoUrl)
                        <button type="button" wire:click="removeLogo"
                                wire:confirm="¿Quitar el logo? Se volverá a mostrar el nombre."
                                class="text-xs text-red-600 hover:underline">Quitar</button>
                    @endif
                </div>
            </div>
            <div wire:loading wire:target="logo" class="mt-1 text-xs text-slate-400">Cargando imagen…</div>
            @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Favicon --}}
        <div class="border-t border-slate-100 pt-6">
            <label class="block text-sm font-medium text-slate-700">Favicon</label>
            <p class="mt-0.5 text-xs text-slate-400">PNG, ICO o SVG. Máx. 512 KB. Es el icono de la pestaña del navegador.</p>

            <div class="mt-3 flex items-center gap-4">
                @if ($favicon && method_exists($favicon, 'isPreviewable') && $favicon->isPreviewable())
                    <img src="{{ $favicon->temporaryUrl() }}" alt="Vista previa del favicon" class="h-8 w-8 rounded bg-slate-100 object-contain p-0.5 ring-1 ring-slate-200">
                @elseif ($faviconUrl)
                    <img src="{{ $faviconUrl }}" alt="Favicon actual" class="h-8 w-8 rounded bg-slate-100 object-contain p-0.5 ring-1 ring-slate-200">
                @else
                    <span class="text-xs text-slate-400">Sin favicon (se usa el predeterminado).</span>
                @endif

                <div class="flex items-center gap-2">
                    <input type="file" wire:model="favicon" accept="image/png,image/svg+xml,image/x-icon,.ico"
                           class="text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700">
                    @if ($faviconUrl)
                        <button type="button" wire:click="removeFavicon"
                                wire:confirm="¿Quitar el favicon?"
                                class="text-xs text-red-600 hover:underline">Quitar</button>
                    @endif
                </div>
            </div>
            <div wire:loading wire:target="favicon" class="mt-1 text-xs text-slate-400">Cargando imagen…</div>
            @error('favicon') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-slate-100 pt-6">
            <button type="submit" wire:loading.attr="disabled" wire:target="save,logo,favicon"
                    class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
                Guardar cambios
            </button>
        </div>
    </form>
</div>
