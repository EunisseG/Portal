<?php

namespace App\Http\Controllers\Client;

use App\Domain\Clients\Services\ClientDashboardSummary;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClientDashboardSummary $summaries): View
    {
        $client = $request->user()->clientAccount();

        $summary = $client
            ? $summaries->for($client)
            : null;

        return view('client.dashboard', [
            'summary' => $summary,
        ]);
    }
}
