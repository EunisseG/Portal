{{-- Script compartido: dibuja gráficas de barras de "distribución por estado". --}}
{{-- Cada <canvas data-status-chart data-labels data-values> se renderiza una vez. --}}
<script>
    (function () {
        // Dibuja el valor numérico encima de cada barra.
        const valueLabels = {
            id: 'valueLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((bar, index) => {
                        ctx.save();
                        ctx.fillStyle = '#334155';
                        ctx.font = '600 12px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(dataset.data[index], bar.x, bar.y - 4);
                        ctx.restore();
                    });
                });
            },
        };

        function renderStatusCharts() {
            if (typeof window.Chart === 'undefined') {
                return;
            }
            document.querySelectorAll('canvas[data-status-chart]').forEach((canvas) => {
                if (canvas.dataset.rendered) {
                    return;
                }
                canvas.dataset.rendered = '1';
                const palette = ['#22c55e', '#f59e0b', '#f97316', '#64748b', '#ef4444'];
                const values = JSON.parse(canvas.dataset.values);
                new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: JSON.parse(canvas.dataset.labels),
                        datasets: [{
                            label: 'Cantidad',
                            data: values,
                            backgroundColor: values.map((_, idx) => palette[idx % palette.length]),
                            borderWidth: 0,
                            borderRadius: 6,
                            maxBarThickness: 64,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 16 } },
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                    },
                    plugins: [valueLabels],
                });
            });
        }

        document.addEventListener('DOMContentLoaded', renderStatusCharts);
        document.addEventListener('livewire:navigated', renderStatusCharts);

        // Al cambiar de pestaña Livewire re-renderiza sin recargar: tras cada
        // respuesta se dibujan las gráficas nuevas (las ya dibujadas se omiten).
        function hookLivewire() {
            if (window.__statusChartsHooked || ! window.Livewire || ! window.Livewire.hook) {
                return;
            }
            window.__statusChartsHooked = true;
            window.Livewire.hook('commit', ({ succeed }) => {
                succeed(() => setTimeout(renderStatusCharts, 50));
            });
        }
        hookLivewire();
        document.addEventListener('livewire:init', hookLivewire);
        document.addEventListener('DOMContentLoaded', hookLivewire);
    })();
</script>
