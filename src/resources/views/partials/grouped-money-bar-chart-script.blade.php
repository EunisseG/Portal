{{-- Gráfica de barras agrupadas de montos en dinero (MXN) con dos series. --}}
{{-- Cada <canvas data-grouped-money-chart data-labels data-series='[{label,data,color}]'>. --}}
<script>
    (function () {
        const mxn = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits: 0,
        });

        function renderGroupedMoneyCharts() {
            if (typeof window.Chart === 'undefined') {
                return;
            }
            document.querySelectorAll('canvas[data-grouped-money-chart]').forEach((canvas) => {
                if (canvas.dataset.rendered) {
                    return;
                }
                canvas.dataset.rendered = '1';
                const labels = JSON.parse(canvas.dataset.labels);
                const series = JSON.parse(canvas.dataset.series);

                new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: series.map((s) => ({
                            label: s.label,
                            data: s.data,
                            backgroundColor: s.color,
                            borderWidth: 0,
                            borderRadius: 6,
                            maxBarThickness: 48,
                        })),
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ctx.dataset.label + ': ' + mxn.format(ctx.parsed.y),
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: (value) => mxn.format(value) },
                            },
                        },
                    },
                });
            });
        }

        document.addEventListener('DOMContentLoaded', renderGroupedMoneyCharts);
        document.addEventListener('livewire:navigated', renderGroupedMoneyCharts);
        document.addEventListener('livewire:update', renderGroupedMoneyCharts);
    })();
</script>
