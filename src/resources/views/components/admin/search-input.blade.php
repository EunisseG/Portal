@props([
    // Propiedad Livewire a enlazar (p. ej. 'search').
    'model' => 'search',
    'placeholder' => 'Buscar...',
    // Milisegundos de debounce para la búsqueda en vivo.
    'debounce' => '300',
    // Clase de ancho del contenedor (sobrescribible por pantalla).
    'width' => 'w-full max-w-sm',
    // Texto de ayuda bajo el campo. Pasa :hint="false" para ocultarlo.
    'hint' => 'La búsqueda se aplica automáticamente mientras escribes.',
])

{{-- Buscador con lupa + indicador de "Buscando…" (feedback de wire:loading). --}}
<div {{ $attributes->only('class') }}>
    <div class="relative {{ $width }}">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
        </span>
        <input type="search"
               wire:model.live.debounce.{{ $debounce }}ms="{{ $model }}"
               placeholder="{{ $placeholder }}"
               class="block w-full rounded-md border-slate-300 pl-9 pr-9 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        {{-- Spinner mientras Livewire procesa la búsqueda. --}}
        <span wire:loading wire:target="{{ $model }}"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-indigo-500">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </span>
    </div>
    @if ($hint !== false)
        <p class="mt-1 text-xs text-slate-400">
            <span wire:loading.remove wire:target="{{ $model }}">{{ $hint }}</span>
            <span wire:loading wire:target="{{ $model }}" class="text-indigo-500">Buscando…</span>
        </p>
    @endif
</div>
