<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Models\Audit;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Models\Ticket;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Arma todas las métricas del escritorio administrativo (estilo panel de control).
 */
class AdminDashboardStats
{
    /**
     * Tarjetas superiores (KPIs).
     *
     * @return array<string, array<string, int|string>>
     */
    public function cards(): array
    {
        return [
            'clients' => [
                'total' => Client::count(),
                'suspended' => Client::whereHas('user', fn ($q) => $q->where('status', 'blocked'))->count(),
            ],
            'orders' => [
                'total' => Service::count(),
                'suspended' => Service::where('status', ServiceStatus::Suspended->value)->count(),
            ],
            'invoices' => [
                'total' => Invoice::count(),
                'unpaid' => Invoice::whereIn('status', [InvoiceStatus::Pending->value, InvoiceStatus::Overdue->value])->count(),
            ],
            'tickets' => [
                'total' => Ticket::count(),
                'open' => Ticket::whereIn('status', [TicketStatus::Open->value, TicketStatus::Pending->value])->count(),
            ],
        ];
    }

    /**
     * Tabla de estadísticas por periodo: hoy, ayer, este mes, mes pasado, total.
     *
     * @return array<string, array<string, string|int>>
     */
    public function statistics(): array
    {
        $periods = $this->periods();

        $rows = [];

        // Ingresos: suma del total de facturas PAGADAS por su fecha de emisión.
        $rows['income'] = [];
        foreach ($periods as $key => [$from, $to]) {
            $cents = Invoice::where('status', InvoiceStatus::Paid->value)
                ->when($from, fn ($q) => $q->whereBetween('issued_at', [$from, $to]))
                ->sum('total_cents');
            $rows['income'][$key] = (new Money((int) $cents))->format();
        }

        $rows['clients'] = $this->countByPeriod(Client::query(), $periods);
        $rows['orders'] = $this->countByPeriod(Service::query(), $periods);
        $rows['invoices'] = $this->countByPeriod(Invoice::query(), $periods);
        $rows['tickets'] = $this->countByPeriod(Ticket::query(), $periods);

        return $rows;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    public function recentInvoices(int $limit = 5)
    {
        return Invoice::with('client.user')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Service>
     */
    public function recentOrders(int $limit = 5)
    {
        return Service::with('client.user')->orderByDesc('id')->limit($limit)->get();
    }


    /**
     * Ventas por producto: servicios agrupados por nombre con su conteo.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function productSales(int $limit = 5)
    {
        return Service::query()
            ->selectRaw('name, count(*) as total')
            ->groupBy('name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Audit>
     */
    public function recentActivity(int $limit = 8)
    {
        return Audit::with('user')->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * Tickets agrupados por estado, listo para una gráfica de dona. Se puede
     * acotar por tipo de ticket ('client' | 'internal'); sin tipo, todos.
     *
     * @return array{labels: list<string>, data: list<int>, colors: list<string>}
     */
    public function ticketsByStatus(?\App\Domain\Tickets\Enums\TicketType $type = null): array
    {
        $counts = Ticket::query()
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $colors = [
            TicketStatus::Open->value => '#3b82f6',
            TicketStatus::Pending->value => '#f59e0b',
            TicketStatus::Answered->value => '#22c55e',
            TicketStatus::Resolved->value => '#10b981',
            TicketStatus::Closed->value => '#94a3b8',
        ];

        // Fallback defensivo: cualquier estado sin color definido usa gris.
        $colorFor = fn (string $value): string => $colors[$value] ?? '#cbd5e1';

        $labels = [];
        $data = [];
        $palette = [];

        foreach (TicketStatus::cases() as $status) {
            $labels[] = $status->label();
            $data[] = (int) ($counts[$status->value] ?? 0);
            $palette[] = $colorFor($status->value);
        }

        return ['labels' => $labels, 'data' => $data, 'colors' => $palette];
    }

    /**
     * Tickets creados por día en los últimos N días (para gráfica de línea).
     * Se puede acotar por tipo ('client' | 'internal'); sin tipo, todos.
     *
     * @return array{labels: list<string>, data: list<int>}
     */
    public function ticketsByDay(int $days = 30, ?\App\Domain\Tickets\Enums\TicketType $type = null): array
    {
        $from = Carbon::now()->startOfDay()->subDays($days - 1);

        $counts = Ticket::query()
            ->where('created_at', '>=', $from)
            ->when($type, fn ($q) => $q->where('type', $type->value))
            ->get(['created_at'])
            ->groupBy(fn ($ticket) => $ticket->created_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d M');
            $data[] = (int) ($counts[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Rangos de fecha por periodo. 'total' => [null, null] (sin filtro).
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    private function periods(): array
    {
        $now = Carbon::now();

        return [
            'today' => [$now->copy()->startOfDay()->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()],
            'yesterday' => [$now->copy()->subDay()->startOfDay()->toDateTimeString(), $now->copy()->subDay()->endOfDay()->toDateTimeString()],
            'this_month' => [$now->copy()->startOfMonth()->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateTimeString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateTimeString()],
            'total' => [null, null],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, array{0: ?string, 1: ?string}>  $periods
     * @return array<string, int>
     */
    private function countByPeriod($query, array $periods): array
    {
        $result = [];
        foreach ($periods as $key => [$from, $to]) {
            $result[$key] = (clone $query)
                ->when($from, fn ($q) => $q->whereBetween('created_at', [$from, $to]))
                ->count();
        }

        return $result;
    }
}
