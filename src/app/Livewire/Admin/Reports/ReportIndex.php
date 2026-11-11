<?php

namespace App\Livewire\Admin\Reports;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Clients\Enums\ClientStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Product;
use App\Domain\Sales\Enums\SalesEventType;
use App\Domain\Sales\Models\SalesEvent;
use App\Domain\Sales\Models\SalesRep;
use App\Domain\Services\Models\Service;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Concentrador de gráficas (reportes) del panel admin. Reúne en una sola
 * pantalla las distribuciones por estado de los distintos módulos y una
 * gráfica de ingresos por facturas pagadas (dinero cobrado) por mes.
 *
 * Admite un filtro por rango de fechas: las distribuciones se filtran por la
 * fecha de creación de cada registro y los ingresos por la fecha de pago. Por
 * defecto cubre los últimos 12 meses.
 *
 * Es solo lectura y se protege con el mismo permiso que las facturas
 * (invoices.view), de modo que lo ven quienes ya acceden a facturación.
 */
class ReportIndex extends Component
{
    /** Fecha inicial del rango (inclusive), formato Y-m-d. */
    #[Url(as: 'from')]
    public string $from = '';

    /** Fecha final del rango (inclusive), formato Y-m-d. */
    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('reports.view');

        // Rango por defecto: últimos 12 meses (inicio de mes hace 11 meses → hoy).
        if ($this->from === '') {
            $this->from = Carbon::now()->startOfMonth()->subMonths(11)->toDateString();
        }
        if ($this->to === '') {
            $this->to = Carbon::now()->toDateString();
        }
    }

    /** Aplica (normaliza) el rango. El re-render recalcula todo. */
    public function applyFilter(): void
    {
        $this->normalizeRange();
    }

    /** Restablece el rango por defecto (últimos 12 meses). */
    public function resetFilter(): void
    {
        $this->from = Carbon::now()->startOfMonth()->subMonths(11)->toDateString();
        $this->to = Carbon::now()->toDateString();
    }

    /** Fecha inicial resuelta (Carbon al inicio del día). */
    private function fromDate(): Carbon
    {
        return Carbon::parse($this->from)->startOfDay();
    }

    /** Fecha final resuelta (Carbon al fin del día). */
    private function toDate(): Carbon
    {
        return Carbon::parse($this->to)->endOfDay();
    }

    /** Garantiza from <= to; si se invierten, los intercambia. */
    private function normalizeRange(): void
    {
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
    }

    /**
     * Cuenta registros por estado dentro del rango de fechas (created_at),
     * devolviendo un bloque de gráfica (labels + data + total).
     *
     * @param  array<int, array{value: string, label: string}>  $states
     * @return array{labels: array<int, string>, data: array<int, int>, total: int}
     */
    private function statusChart(Builder $query, string $column, array $states): array
    {
        $counts = $query
            ->whereDate('created_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('created_at', '<=', $this->toDate()->toDateString())
            ->selectRaw("{$column} as st, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'st');

        $labels = [];
        $data = [];
        $total = 0;

        foreach ($states as $state) {
            $value = (int) ($counts[$state['value']] ?? 0);
            $labels[] = $state['label'];
            $data[] = $value;
            $total += $value;
        }

        return ['labels' => $labels, 'data' => $data, 'total' => $total];
    }

    /**
     * Ingresos por mes = suma de pagos registrados (dinero efectivamente
     * cobrado), agrupados por el mes de la fecha de pago, dentro del rango.
     * Incluye meses sin ingresos (en cero). Si el rango abarca muchos años, se
     * limita a 60 meses para no generar una gráfica inmanejable.
     *
     * Se agrega en PHP para ser portable entre MySQL (producción) y SQLite
     * (pruebas), evitando funciones de fecha específicas del motor.
     *
     * @return array{labels: array<int, string>, data: array<int, float>, totalCents: int}
     */
    private function revenueByMonth(): array
    {
        $from = $this->fromDate();
        $to = $this->toDate();

        // Suma de centavos por "YYYY-MM" según paid_at, dentro del rango.
        $sums = Payment::query()
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $from->toDateString())
            ->whereDate('paid_at', '<=', $to->toDateString())
            ->get(['amount_cents', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('amount_cents'));

        $labels = [];
        $data = [];
        $totalCents = 0;

        $cursor = $from->copy()->startOfMonth();
        $last = $to->copy()->startOfMonth();
        $guard = 0;
        while ($cursor->lte($last) && $guard < 60) {
            $key = $cursor->format('Y-m');
            $cents = (int) ($sums[$key] ?? 0);
            $labels[] = $cursor->translatedFormat('M Y');
            $data[] = round($cents / 100, 2);
            $totalCents += $cents;
            $cursor = $cursor->copy()->addMonth();
            $guard++;
        }

        return ['labels' => $labels, 'data' => $data, 'totalCents' => $totalCents];
    }

    /**
     * Clientes por ejecutivo de ventas (conteo), filtrado por fecha de registro
     * del cliente. Incluye una categoría "Sin asignar" para los clientes sin
     * ejecutivo. Solo se listan ejecutivos con al menos un cliente en el rango.
     *
     * @return array{labels: array<int, string>, data: array<int, int>, total: int}
     */
    private function clientsByRep(): array
    {
        // El ejecutivo vive ahora en el servicio. Se cuentan los servicios
        // creados en el rango agrupados por su ejecutivo (actividad comercial
        // por ejecutivo). Un servicio sin ejecutivo cae en "Sin asignar".
        $counts = Service::query()
            ->whereDate('created_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('created_at', '<=', $this->toDate()->toDateString())
            ->selectRaw('sales_rep_id, count(*) as total')
            ->groupBy('sales_rep_id')
            ->pluck('total', 'sales_rep_id');

        $names = SalesRep::pluck('name', 'id');

        $labels = [];
        $data = [];
        $total = 0;
        $unassigned = 0;

        foreach ($counts as $repId => $count) {
            if ($repId === null) {
                $unassigned += (int) $count;

                continue;
            }
            $labels[] = $names[$repId] ?? ('#'.$repId);
            $data[] = (int) $count;
            $total += (int) $count;
        }

        if ($unassigned > 0) {
            $labels[] = 'Sin asignar';
            $data[] = $unassigned;
            $total += $unassigned;
        }

        return ['labels' => $labels, 'data' => $data, 'total' => $total];
    }

    /**
     * Ingresos por ejecutivo de ventas: suma de pagos (dinero cobrado) de los
     * clientes de cada ejecutivo, dentro del rango por fecha de pago. Engloba
     * ventas nuevas y renovaciones (ambas terminan en un pago). Los importes se
     * devuelven en pesos.
     *
     * @return array{labels: array<int, string>, data: array<int, float>, total: int}
     */
    private function revenueByRep(): array
    {
        // payments -> invoices -> services.sales_rep_id (el ejecutivo vive en
        // el servicio). Si la factura no está ligada a un servicio, el ingreso
        // cae en "Sin asignar".
        $rows = Payment::query()
            ->whereNotNull('payments.paid_at')
            ->whereDate('payments.paid_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('payments.paid_at', '<=', $this->toDate()->toDateString())
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('services', 'services.id', '=', 'invoices.service_id')
            ->selectRaw('services.sales_rep_id as rep_id, SUM(payments.amount_cents) as cents')
            ->groupBy('services.sales_rep_id')
            ->pluck('cents', 'rep_id');

        $names = SalesRep::pluck('name', 'id');

        $labels = [];
        $data = [];
        $total = 0;
        $unassignedCents = 0;

        foreach ($rows as $repId => $cents) {
            if ($repId === null) {
                $unassignedCents += (int) $cents;

                continue;
            }
            $labels[] = $names[$repId] ?? ('#'.$repId);
            $data[] = round(((int) $cents) / 100, 2);
            $total += (int) $cents;
        }

        if ($unassignedCents > 0) {
            $labels[] = 'Sin asignar';
            $data[] = round($unassignedCents / 100, 2);
            $total += $unassignedCents;
        }

        return ['labels' => $labels, 'data' => $data, 'total' => $total];
    }

    /**
     * Ventas nuevas vs renovaciones por ejecutivo (importe en pesos), según los
     * eventos comerciales (sales_events) dentro del rango por fecha del evento.
     * Devuelve dos series alineadas al mismo arreglo de etiquetas (ejecutivos).
     *
     * @return array{labels: array<int, string>, newSales: array<int, float>, renewals: array<int, float>, total: int}
     */
    private function salesVsRenewalsByRep(): array
    {
        $rows = SalesEvent::query()
            ->whereDate('occurred_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('occurred_at', '<=', $this->toDate()->toDateString())
            ->selectRaw('sales_rep_id, type, SUM(amount_cents) as cents')
            ->groupBy('sales_rep_id', 'type')
            ->get();

        $names = SalesRep::pluck('name', 'id');

        // Acumula por ejecutivo: [repKey => ['new' => cents, 'ren' => cents, 'label' => ...]].
        $byRep = [];
        $total = 0;

        foreach ($rows as $row) {
            $key = $row->sales_rep_id ?? 0; // 0 = sin asignar
            if (! isset($byRep[$key])) {
                $byRep[$key] = [
                    'label' => $row->sales_rep_id ? ($names[$row->sales_rep_id] ?? ('#'.$row->sales_rep_id)) : 'Sin asignar',
                    'new' => 0,
                    'ren' => 0,
                ];
            }
            $cents = (int) $row->cents;
            if ($row->type === SalesEventType::Renewal) {
                $byRep[$key]['ren'] += $cents;
            } else {
                $byRep[$key]['new'] += $cents;
            }
            $total += $cents;
        }

        // Ordena: ejecutivos asignados primero (por nombre), "Sin asignar" al final.
        uasort($byRep, function ($a, $b) {
            if ($a['label'] === 'Sin asignar') {
                return 1;
            }
            if ($b['label'] === 'Sin asignar') {
                return -1;
            }

            return strcmp($a['label'], $b['label']);
        });

        $labels = [];
        $newSales = [];
        $renewals = [];
        foreach ($byRep as $rep) {
            $labels[] = $rep['label'];
            $newSales[] = round($rep['new'] / 100, 2);
            $renewals[] = round($rep['ren'] / 100, 2);
        }

        return ['labels' => $labels, 'newSales' => $newSales, 'renewals' => $renewals, 'total' => $total];
    }

    public function render(): View
    {
        $this->normalizeRange();

        $invoiceChart = $this->statusChart(Invoice::query(), 'status', [
            ['value' => InvoiceStatus::Pending->value, 'label' => InvoiceStatus::Pending->label()],
            ['value' => InvoiceStatus::Paid->value, 'label' => InvoiceStatus::Paid->label()],
            ['value' => InvoiceStatus::Overdue->value, 'label' => InvoiceStatus::Overdue->label()],
            ['value' => InvoiceStatus::Cancelled->value, 'label' => InvoiceStatus::Cancelled->label()],
        ]);

        $paymentOrderChart = $this->statusChart(PaymentOrder::query(), 'status', [
            ['value' => PaymentOrderStatus::Pending->value, 'label' => PaymentOrderStatus::Pending->label()],
            ['value' => PaymentOrderStatus::Paid->value, 'label' => PaymentOrderStatus::Paid->label()],
            ['value' => PaymentOrderStatus::Expired->value, 'label' => PaymentOrderStatus::Expired->label()],
            ['value' => PaymentOrderStatus::Cancelled->value, 'label' => PaymentOrderStatus::Cancelled->label()],
        ]);

        $serviceChart = $this->statusChart(Service::query(), 'status', [
            ['value' => ServiceStatus::Pending->value, 'label' => ServiceStatus::Pending->label()],
            ['value' => ServiceStatus::Active->value, 'label' => ServiceStatus::Active->label()],
            ['value' => ServiceStatus::Suspended->value, 'label' => ServiceStatus::Suspended->label()],
            ['value' => ServiceStatus::Cancelled->value, 'label' => ServiceStatus::Cancelled->label()],
        ]);

        $domainChart = $this->statusChart(Domain::query(), 'status', [
            ['value' => DomainStatus::Active->value, 'label' => DomainStatus::Active->label()],
            ['value' => DomainStatus::Pending->value, 'label' => DomainStatus::Pending->label()],
            ['value' => DomainStatus::Expired->value, 'label' => DomainStatus::Expired->label()],
            ['value' => DomainStatus::Cancelled->value, 'label' => DomainStatus::Cancelled->label()],
        ]);

        $clientChart = $this->statusChart(Client::query(), 'status', [
            ['value' => ClientStatus::Active->value, 'label' => ClientStatus::Active->label()],
            ['value' => ClientStatus::Suspended->value, 'label' => ClientStatus::Suspended->label()],
            ['value' => ClientStatus::Cancelled->value, 'label' => ClientStatus::Cancelled->label()],
        ]);

        $ticketStates = [
            ['value' => TicketStatus::Open->value, 'label' => TicketStatus::Open->label()],
            ['value' => TicketStatus::Pending->value, 'label' => TicketStatus::Pending->label()],
            ['value' => TicketStatus::Answered->value, 'label' => TicketStatus::Answered->label()],
            ['value' => TicketStatus::Resolved->value, 'label' => TicketStatus::Resolved->label()],
            ['value' => TicketStatus::Closed->value, 'label' => TicketStatus::Closed->label()],
        ];

        // Tickets de CLIENTES e INTERNOS por separado.
        $ticketChart = $this->statusChart(
            Ticket::query()->where('type', \App\Domain\Tickets\Enums\TicketType::Client->value),
            'status',
            $ticketStates,
        );
        $internalTicketChart = $this->statusChart(
            Ticket::query()->where('type', \App\Domain\Tickets\Enums\TicketType::Internal->value),
            'status',
            $ticketStates,
        );

        // Productos: no hay enum de estado; se usa is_active (filtrado por fecha de creación).
        $productBase = Product::query()
            ->whereDate('created_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('created_at', '<=', $this->toDate()->toDateString());
        $productActive = (int) (clone $productBase)->where('is_active', true)->count();
        $productInactive = (int) (clone $productBase)->where('is_active', false)->count();
        $productChart = [
            'labels' => ['Habilitados', 'Deshabilitados'],
            'data' => [$productActive, $productInactive],
            'total' => $productActive + $productInactive,
        ];

        $revenue = $this->revenueByMonth();
        $clientsByRep = $this->clientsByRep();
        $revenueByRep = $this->revenueByRep();
        $salesVsRenewals = $this->salesVsRenewalsByRep();

        return view('livewire.admin.reports.report-index', [
            'invoiceChart' => $invoiceChart,
            'paymentOrderChart' => $paymentOrderChart,
            'serviceChart' => $serviceChart,
            'domainChart' => $domainChart,
            'clientChart' => $clientChart,
            'ticketChart' => $ticketChart,
            'internalTicketChart' => $internalTicketChart,
            'productChart' => $productChart,
            'revenue' => $revenue,
            'clientsByRep' => $clientsByRep,
            'revenueByRep' => $revenueByRep,
            'salesVsRenewals' => $salesVsRenewals,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
