<div>
    <h1 class="text-2xl font-semibold text-slate-900">Satisfacción</h1>
    <p class="mt-1 text-slate-600">Resultados de la encuesta que se genera al resolver o cerrar cada ticket.</p>

    {{-- Pestañas: clientes / internos --}}
    <div class="mt-4 flex gap-2">
        <button wire:click="setType('client')"
                class="rounded-md px-3 py-1.5 text-sm font-medium {{ $ticketType === 'client' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
            Clientes
        </button>
        <button wire:click="setType('internal')"
                class="rounded-md px-3 py-1.5 text-sm font-medium {{ $ticketType === 'internal' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
            Internos (empleados)
        </button>
    </div>

    {{-- KPIs --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm text-slate-500">Promedio CSAT</div>
            <div class="mt-1 text-3xl font-semibold text-slate-900">{{ number_format($average, 2) }}<span class="text-lg text-slate-400"> / 5</span></div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm text-slate-500">Respondidas</div>
            <div class="mt-1 text-3xl font-semibold text-green-700">{{ $answered }}</div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="text-sm text-slate-500">Pendientes</div>
            <div class="mt-1 text-3xl font-semibold text-amber-600">{{ $pending }}</div>
        </div>
    </div>

    {{-- Gráfica de distribución --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Distribución de puntuaciones</h2>
        <div class="mt-4 h-72">
            <canvas id="csatChart" wire:key="csat-{{ $ticketType }}"
                    data-labels='@json($distribution['labels'])'
                    data-values='@json($distribution['data'])'></canvas>
        </div>
    </div>

    {{-- Resultados por pregunta --}}
    <div class="mt-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
        <h2 class="font-semibold text-slate-900">Resultados por pregunta</h2>
        @if (count($questionBreakdown) === 0)
            <p class="mt-3 text-sm text-slate-500">Esta encuesta no tiene preguntas activas.</p>
        @else
            <div class="mt-4 space-y-4">
                @foreach ($questionBreakdown as $q)
                    <div class="rounded-lg ring-1 ring-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="font-medium text-slate-800">{{ $q['label'] }}</div>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">{{ $q['type_label'] }}</span>
                        </div>

                        @if ($q['type'] === 'scale')
                            <div class="mt-2 text-sm text-slate-600">
                                Promedio:
                                <span class="font-semibold text-slate-900">{{ $q['average'] !== null ? $q['average'].' / 5' : '—' }}</span>
                                <span class="text-slate-400">· {{ $q['count'] }} respuesta(s)</span>
                            </div>
                        @elseif ($q['type'] === 'boolean')
                            <div class="mt-2 text-sm text-slate-600">
                                <span class="font-semibold text-green-700">Sí: {{ $q['yes'] }}</span>
                                · <span class="font-semibold text-slate-700">No: {{ $q['no'] }}</span>
                                <span class="text-slate-400">· {{ $q['count'] }} respuesta(s)</span>
                            </div>
                        @else
                            <div class="mt-2 text-sm text-slate-500">{{ $q['count'] }} comentario(s).</div>
                            @if (! empty($q['recent']))
                                <ul class="mt-2 space-y-1">
                                    @foreach ($q['recent'] as $txt)
                                        <li class="rounded bg-slate-50 px-3 py-1.5 text-sm text-slate-600">“{{ \Illuminate\Support\Str::limit($txt, 120) }}”</li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Comentarios recientes --}}
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="border-b border-slate-100 px-5 py-3">
            <h2 class="font-semibold text-slate-900">Respuestas recientes</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3">Ticket</th>
                    <th class="px-5 py-3">{{ $ticketType === 'internal' ? 'Empleado' : 'Cliente' }}</th>
                    <th class="px-5 py-3">Puntuación</th>
                    <th class="px-5 py-3">Comentario</th>
                    <th class="px-5 py-3">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($recent as $survey)
                    <tr wire:key="survey-{{ $survey->id }}">
                        <td class="px-5 py-3 text-slate-600">{{ $survey->ticket?->number }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ $ticketType === 'internal' ? ($survey->user?->name ?? '—') : ($survey->client?->user?->name ?? '—') }}</td>
                        <td class="px-5 py-3 font-semibold text-slate-800">{{ $survey->score }}/5</td>
                        <td class="px-5 py-3 text-slate-500">{{ $survey->comment ? \Illuminate\Support\Str::limit($survey->comment, 60) : '—' }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $survey->responded_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">Aún no hay encuestas respondidas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script>
            (function () {
                function renderCsatChart() {
                    if (typeof window.Chart === 'undefined') {
                        return;
                    }
                    const canvas = document.getElementById('csatChart');
                    if (!canvas || canvas.dataset.rendered) {
                        return;
                    }
                    canvas.dataset.rendered = '1';
                    new window.Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: JSON.parse(canvas.dataset.labels),
                            datasets: [{
                                label: 'Respuestas',
                                data: JSON.parse(canvas.dataset.values),
                                backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        },
                    });
                }

                document.addEventListener('DOMContentLoaded', renderCsatChart);
                document.addEventListener('livewire:navigated', renderCsatChart);
            })();
        </script>
    @endpush
</div>
