<?php

namespace App\Livewire\Client\Tickets;

use App\Domain\Tickets\Models\Ticket;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class TicketIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->canClient('client.tickets.view'), 403);
    }

    public function render(): View
    {
        $client = auth()->user()->clientAccount();

        $tickets = $client
            ? Ticket::where('client_id', $client->id)
                ->orderByDesc('last_reply_at')
                ->paginate(10)
            : Ticket::query()->whereRaw('1 = 0')->paginate(10);

        return view('livewire.client.tickets.ticket-index', [
            'tickets' => $tickets,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
