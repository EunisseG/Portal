<?php

namespace App\Domain\Clients\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Tickets\Enums\TicketStatus;

/**
 * Arma el resumen del escritorio de un cliente (perfil, servicios, facturas,
 * tickets). Reutilizable por el escritorio del cliente y por la vista admin
 * de solo lectura.
 */
class ClientDashboardSummary
{
    /** Días para considerar un servicio/dominio "próximo a vencer". */
    public const EXPIRING_DAYS = 30;

    /** Máximo de filas en las listas del escritorio. */
    public const LIST_LIMIT = 5;

    /**
     * @return array<string, mixed>
     */
    public function for(Client $client): array
    {
        $client->loadMissing('user');

        $servicesQuery = $client->services();
        $total = (clone $servicesQuery)->count();
        $active = (clone $servicesQuery)->where('status', ServiceStatus::Active->value)->count();
        $expiringSoon = (clone $servicesQuery)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(self::EXPIRING_DAYS)->toDateString()])
            ->count();

        $invoicesQuery = $client->invoices();
        $invoicesTotal = (clone $invoicesQuery)->count();
        $invoicesPaid = (clone $invoicesQuery)->where('status', InvoiceStatus::Paid->value)->count();
        $invoicesUnpaid = (clone $invoicesQuery)->whereIn('status', [
            InvoiceStatus::Pending->value,
            InvoiceStatus::Overdue->value,
        ])->count();

        $ticketsQuery = $client->tickets();
        $ticketsTotal = (clone $ticketsQuery)->count();
        $ticketsOpen = (clone $ticketsQuery)->whereIn('status', [
            TicketStatus::Open->value,
            TicketStatus::Answered->value,
        ])->count();
        $ticketsPending = (clone $ticketsQuery)->where('status', TicketStatus::Pending->value)->count();

        // Servicios próximos a vencer (siguientes 30 días), ordenados por fecha.
        $upcomingServices = (clone $servicesQuery)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays(self::EXPIRING_DAYS)->toDateString()])
            ->orderBy('expires_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        // Facturas más recientes por fecha de emisión.
        $recentInvoices = (clone $invoicesQuery)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        return [
            'profile' => [
                'name' => $client->user->name,
                'client_id' => $client->id,
                'email' => $client->user->email,
                'company' => $client->company,
                // El ejecutivo ahora es por servicio; el cliente puede tener
                // varios. Se listan los distintos que llevan sus servicios.
                'sales_reps' => $client->salesReps()->map(fn ($rep) => [
                    'name' => $rep->name,
                    'email' => $rep->email,
                    'phone' => $rep->phone,
                ])->all(),
            ],
            'credit' => [
                'balance' => $client->creditBalance()->format(),
                'has_balance' => (int) ($client->credit_balance_cents ?? 0) > 0,
            ],
            'services' => [
                'total' => $total,
                'active' => $active,
                'expiring_soon' => $expiringSoon,
            ],
            'invoices' => [
                'total' => $invoicesTotal,
                'paid' => $invoicesPaid,
                'unpaid' => $invoicesUnpaid,
            ],
            'tickets' => [
                'total' => $ticketsTotal,
                'open' => $ticketsOpen,
                'pending' => $ticketsPending,
            ],
            'upcoming_services' => $upcomingServices,
            'recent_invoices' => $recentInvoices,
        ];
    }
}
