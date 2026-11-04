<?php

namespace App\Exports;

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
use App\Domain\Services\Models\Service;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportación del concentrador de gráficas: distribución por estado de cada
 * módulo (filtrada por fecha de registro) e ingresos por mes (por fecha de
 * pago), dentro del rango indicado. Pensado para Excel o CSV.
 */
class ReportExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly string $from,
        private readonly string $to,
    ) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Sección', 'Concepto', 'Valor'];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string|int|float}>
     */
    public function array(): array
    {
        $rows = [];

        // Encabezado informativo del periodo.
        $rows[] = ['Periodo', 'Desde', $this->from];
        $rows[] = ['Periodo', 'Hasta', $this->to];

        // Distribuciones por estado.
        foreach ($this->distributions() as $section => $counts) {
            foreach ($counts as $label => $value) {
                $rows[] = [$section, $label, $value];
            }
        }

        // Ingresos por mes (en pesos).
        foreach ($this->revenueByMonth() as $month => $amount) {
            $rows[] = ['Ingresos por mes', $month, number_format($amount, 2)];
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function distributions(): array
    {
        return [
            'Facturas por estado' => $this->statusCounts(Invoice::query(), 'status', [
                InvoiceStatus::Pending->value => InvoiceStatus::Pending->label(),
                InvoiceStatus::Paid->value => InvoiceStatus::Paid->label(),
                InvoiceStatus::Overdue->value => InvoiceStatus::Overdue->label(),
                InvoiceStatus::Cancelled->value => InvoiceStatus::Cancelled->label(),
            ]),
            'Órdenes de pago por estado' => $this->statusCounts(PaymentOrder::query(), 'status', [
                PaymentOrderStatus::Pending->value => PaymentOrderStatus::Pending->label(),
                PaymentOrderStatus::Paid->value => PaymentOrderStatus::Paid->label(),
                PaymentOrderStatus::Expired->value => PaymentOrderStatus::Expired->label(),
                PaymentOrderStatus::Cancelled->value => PaymentOrderStatus::Cancelled->label(),
            ]),
            'Pedidos por estado' => $this->statusCounts(Service::query(), 'status', [
                ServiceStatus::Pending->value => ServiceStatus::Pending->label(),
                ServiceStatus::Active->value => ServiceStatus::Active->label(),
                ServiceStatus::Suspended->value => ServiceStatus::Suspended->label(),
                ServiceStatus::Cancelled->value => ServiceStatus::Cancelled->label(),
            ]),
            'Dominios por estado' => $this->statusCounts(Domain::query(), 'status', [
                DomainStatus::Active->value => DomainStatus::Active->label(),
                DomainStatus::Pending->value => DomainStatus::Pending->label(),
                DomainStatus::Expired->value => DomainStatus::Expired->label(),
                DomainStatus::Cancelled->value => DomainStatus::Cancelled->label(),
            ]),
            'Clientes por estado' => $this->statusCounts(Client::query(), 'status', [
                ClientStatus::Active->value => ClientStatus::Active->label(),
                ClientStatus::Suspended->value => ClientStatus::Suspended->label(),
                ClientStatus::Cancelled->value => ClientStatus::Cancelled->label(),
            ]),
            'Tickets por estado' => $this->statusCounts(Ticket::query(), 'status', [
                TicketStatus::Open->value => TicketStatus::Open->label(),
                TicketStatus::Pending->value => TicketStatus::Pending->label(),
                TicketStatus::Answered->value => TicketStatus::Answered->label(),
                TicketStatus::Resolved->value => TicketStatus::Resolved->label(),
                TicketStatus::Closed->value => TicketStatus::Closed->label(),
            ]),
            'Productos por estado' => [
                'Habilitados' => (int) $this->productBase()->where('is_active', true)->count(),
                'Deshabilitados' => (int) $this->productBase()->where('is_active', false)->count(),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $states  value => label
     * @return array<string, int>  label => count
     */
    private function statusCounts(Builder $query, string $column, array $states): array
    {
        $counts = $query
            ->whereDate('created_at', '>=', $this->from)
            ->whereDate('created_at', '<=', $this->to)
            ->selectRaw("{$column} as st, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'st');

        $result = [];
        foreach ($states as $value => $label) {
            $result[$label] = (int) ($counts[$value] ?? 0);
        }

        return $result;
    }

    private function productBase(): Builder
    {
        return Product::query()
            ->whereDate('created_at', '>=', $this->from)
            ->whereDate('created_at', '<=', $this->to);
    }

    /**
     * @return array<string, float>  "YYYY-MM" => importe en pesos
     */
    private function revenueByMonth(): array
    {
        $sums = Payment::query()
            ->whereNotNull('paid_at')
            ->whereDate('paid_at', '>=', $this->from)
            ->whereDate('paid_at', '<=', $this->to)
            ->get(['amount_cents', 'paid_at'])
            ->groupBy(fn (Payment $p) => $p->paid_at->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('amount_cents'));

        $result = [];
        $cursor = Carbon::parse($this->from)->startOfMonth();
        $last = Carbon::parse($this->to)->startOfMonth();
        $guard = 0;
        while ($cursor->lte($last) && $guard < 60) {
            $key = $cursor->format('Y-m');
            $result[$key] = round(((int) ($sums[$key] ?? 0)) / 100, 2);
            $cursor = $cursor->copy()->addMonth();
            $guard++;
        }

        return $result;
    }
}
