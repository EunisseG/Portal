@php
    /** Clases de badge por severidad. */
    $sevBadge = [
        'critical' => 'bg-red-100 text-red-800',
        'high' => 'bg-orange-100 text-orange-800',
        'medium' => 'bg-amber-100 text-amber-800',
        'low' => 'bg-slate-100 text-slate-600',
    ];
    $sevLabel = [
        'critical' => 'Crítica',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baja',
    ];
@endphp

<div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Dependencias / Seguridad</h1>
            <p class="mt-1 text-slate-600">
                Revisión de los paquetes de Composer (PHP) y npm (frontend) contra las bases
                de avisos de seguridad, para no quedar con versiones vulnerables u obsoletas.
                La revisión automática corre cada semana.
            </p>
        </div>
        <button type="button" wire:click="runNow"
                wire:loading.attr="disabled" wire:target="runNow"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60">
            <span wire:loading.remove wire:target="runNow">Revisar ahora</span>
            <span wire:loading wire:target="runNow">Revisando…</span>
        </button>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
            {{ session('status') }}
        </div>
    @endif

    <div wire:loading wire:target="runNow" class="mt-4 rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-700 ring-1 ring-blue-600/10">
        Ejecutando auditoría de dependencias. Esto puede tardar un momento…
    </div>

    @if (! $latest)
        <div class="mt-6 rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-900/5">
            <p class="text-slate-500">Aún no se ha ejecutado ninguna auditoría. Pulsa «Revisar ahora» para hacer la primera.</p>
        </div>
    @else
        {{-- Resumen de la última corrida --}}
        <p class="mt-6 text-sm text-slate-500">
            Última revisión: <span class="font-medium text-slate-700">{{ $latest->created_at->format('d/m/Y H:i') }}</span>
            ({{ $latest->created_at->diffForHumans() }})
        </p>

        @if ($latest->has_errors)
            <div class="mt-3 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-600/10">
                Alguna herramienta de auditoría no pudo ejecutarse (por ejemplo, sin acceso a internet
                en el servidor). Los conteos mostrados pueden estar incompletos.
            </div>
        @endif

        @if ($latest->total_vulnerabilities === 0 && ! $latest->has_errors)
            <div class="mt-3 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-600/10">
                Sin vulnerabilidades conocidas. Todo en orden.
            </div>
        @endif

        {{-- Tarjetas por ecosistema --}}
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['label' => 'Composer (PHP)', 'key' => 'composer'],
                ['label' => 'npm (frontend)', 'key' => 'npm'],
            ] as $eco)
                @php $k = $eco['key']; @endphp
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-900">{{ $eco['label'] }}</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $latest->{$k.'_total'} > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                            {{ $latest->{$k.'_total'} }} {{ $latest->{$k.'_total'} === 1 ? 'vulnerabilidad' : 'vulnerabilidades' }}
                        </span>
                    </div>
                    <dl class="mt-4 grid grid-cols-4 gap-2 text-center">
                        @foreach (['critical', 'high', 'medium', 'low'] as $sev)
                            <div class="rounded-md px-2 py-2 {{ $sevBadge[$sev] }}">
                                <dt class="text-[11px] font-medium uppercase tracking-wide opacity-80">{{ $sevLabel[$sev] }}</dt>
                                <dd class="text-lg font-bold">{{ $latest->{$k.'_'.$sev} }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>

        {{-- Paquetes afectados --}}
        @foreach ([
            ['label' => 'Composer (PHP)', 'key' => 'composer'],
            ['label' => 'npm (frontend)', 'key' => 'npm'],
        ] as $eco)
            @php
                $k = $eco['key'];
                $detail = $latest->detail[$k] ?? [];
                $pkgs = $detail['packages'] ?? [];
                $error = $detail['error'] ?? null;
            @endphp

            @if ($error || count($pkgs) > 0)
                <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                    <div class="border-b border-slate-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-slate-900">Paquetes afectados — {{ $eco['label'] }}</h3>
                    </div>

                    @if ($error)
                        <p class="px-5 py-4 text-sm text-amber-700">{{ $error }}</p>
                    @else
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-2 font-medium">Paquete</th>
                                    <th class="px-5 py-2 font-medium">Severidad</th>
                                    <th class="px-5 py-2 font-medium">Aviso</th>
                                    <th class="px-5 py-2 font-medium">Corrección</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach ($pkgs as $pkg)
                                    <tr>
                                        <td class="px-5 py-2.5 font-medium text-slate-800">{{ $pkg['package'] ?? '—' }}</td>
                                        <td class="px-5 py-2.5">
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $sevBadge[$pkg['severity'] ?? 'low'] ?? $sevBadge['low'] }}">
                                                {{ $sevLabel[$pkg['severity'] ?? 'low'] ?? 'Baja' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-2.5 text-slate-600">
                                            @if (! empty($pkg['link']))
                                                <a href="{{ $pkg['link'] }}" target="_blank" rel="noopener noreferrer"
                                                   class="text-indigo-600 underline hover:text-indigo-800">
                                                    {{ \Illuminate\Support\Str::limit($pkg['title'] ?? 'Ver aviso', 70) }}
                                                </a>
                                            @else
                                                {{ \Illuminate\Support\Str::limit($pkg['title'] ?? '—', 70) }}
                                            @endif
                                        </td>
                                        <td class="px-5 py-2.5 text-slate-600">
                                            @if (array_key_exists('fix_available', $pkg))
                                                {{ $pkg['fix_available'] ? 'Disponible' : 'No disponible' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endif
        @endforeach

        {{-- Paquetes abandonados (Composer) --}}
        @php $abandoned = $latest->detail['composer']['abandoned'] ?? []; @endphp
        @if (count($abandoned) > 0)
            <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h3 class="text-sm font-semibold text-slate-900">Paquetes abandonados (Composer)</h3>
                <p class="mt-1 text-xs text-slate-500">Ya no reciben mantenimiento; conviene reemplazarlos.</p>
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach ($abandoned as $name)
                        <li class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $name }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Historial --}}
        @if ($history->count() > 1)
            <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-slate-900">Revisiones recientes</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-2 font-medium">Fecha</th>
                            <th class="px-5 py-2 font-medium">Composer</th>
                            <th class="px-5 py-2 font-medium">npm</th>
                            <th class="px-5 py-2 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($history as $row)
                            <tr>
                                <td class="px-5 py-2.5 text-slate-600">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-2.5 text-slate-600">{{ $row->composer_total }}</td>
                                <td class="px-5 py-2.5 text-slate-600">{{ $row->npm_total }}</td>
                                <td class="px-5 py-2.5 font-medium text-slate-800">{{ $row->total_vulnerabilities }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
