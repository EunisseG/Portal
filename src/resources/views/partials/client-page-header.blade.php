{{--
    Encabezado de página del área de cliente.
    Espera:
      $title    (string)  título de la página
      $subtitle (string)  descripción breve (opcional)
      $action   (string)  HTML del botón de acción a la derecha (opcional)
--}}
<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="mt-1 text-slate-600">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! empty($action))
        <div class="shrink-0">{!! $action !!}</div>
    @endif
</div>
