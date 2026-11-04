<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reporting\Services\AdminDashboardStats;
use App\Domain\Reporting\Services\SatisfactionStats;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardStats $stats, SatisfactionStats $csat): View
    {
        return view('admin.dashboard', [
            'cards' => $stats->cards(),
            'statistics' => $stats->statistics(),
            'recentInvoices' => $stats->recentInvoices(),
            'recentOrders' => $stats->recentOrders(),
            'productSales' => $stats->productSales(),
            'recentActivity' => $stats->recentActivity(),
            'ticketsByStatusClient' => $stats->ticketsByStatus(\App\Domain\Tickets\Enums\TicketType::Client),
            'ticketsByStatusInternal' => $stats->ticketsByStatus(\App\Domain\Tickets\Enums\TicketType::Internal),
            'ticketsByDayClient' => $stats->ticketsByDay(30, \App\Domain\Tickets\Enums\TicketType::Client),
            'ticketsByDayInternal' => $stats->ticketsByDay(30, \App\Domain\Tickets\Enums\TicketType::Internal),
            'csat' => [
                'average' => $csat->average(),
                'answered' => $csat->answered(),
                'pending' => $csat->pending(),
            ],
        ]);
    }
}
