{{--
    Editor de texto enriquecido ligado a una propiedad Livewire.
    Uso: <x-rich-editor model="body" placeholder="Escribe tu respuesta..." label="Respuesta" />
    El HTML resultante se sanitiza en el servidor (App\Support\RichText) antes de guardarse.
--}}
@props(['model' => 'body', 'placeholder' => '', 'label' => 'Mensaje'])

<div {{ $attributes->merge(['class' => 'rich-editor']) }}
     wire:ignore
     x-data="richEditor(@js($model), @js($placeholder), @js($label))">
    <div x-ref="editor"></div>
</div>
