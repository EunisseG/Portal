{{-- Script compartido: gráfica de barras de montos en dinero (MXN). --}}
{{-- Cada <canvas data-money-chart data-labels data-values> se renderiza una vez. --}}
{{-- A diferencia de status-bar-chart, formatea los valores como moneda MXN. --}}
<script>
    (function () {
        const mxn = new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits: 0,
        });

        // Dibuja el monto formateado encima de cada barra.
        const moneyLabels = {
            id: 'moneyLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                chart.data.datasets.forEach((dataset, i) => {
                    const meta = chart.getDatasetMeta(i);
                    meta.data.forEach((bar, index) => {
                        const value = dataset.data[index];
                        if (! value) {
                            return;
                        }
                        ctx.save();
                        ctx.fillStyle = '#334155';
                        ctx.font = '600 11px sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillText(mxn.format(value), bar.x, bar.y - 4);
                        ctx.restore();
                    });
                });
            },
        };

        function renderMoneyCharts() {
            if (typeof window.Chart === 'undefined') {
                return;
            }
            document.querySelectorAll('canvas[data-money-chart]').forEach((canvas) => {
                if (canvas.dataset.rendered) {
                    return;
                }
                canvas.dataset.rendered = '1';
                const values = JSON.parse(canvas.dataset.values);
                new window.Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: JSON.parse(canvas.dataset.labels),
                        datasets: [{
                            label: 'Ingresos',
                            data: values,
                            backgroundColor: '#16a34a',
                            borderWidth: 0,
                            borderRadius: 6,
                            maxBarThickness: 48,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 20 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => mxn.format(ctx.parsed.y),
                                },
                            },
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: (value) => mxn.format(value),
                                },
                            },
                        },
                    },
                    plugins: [moneyLabels],
                });
            });
        }

        document.addEventListener('DOMContentLoaded', renderMoneyCharts);
        document.addEventListener('livewire:navigated', renderMoneyCharts);
        document.addEventListener('livewire:update', renderMoneyCharts);
    })();
</script>
