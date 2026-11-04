<?php

namespace App\Livewire\Admin\Satisfaction;

use App\Domain\Reporting\Services\SatisfactionStats;
use App\Domain\Tickets\Enums\TicketType;
use App\Domain\Tickets\Models\SatisfactionSurvey;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class SatisfactionIndex extends Component
{
    /** Tipo mostrado: 'client' | 'internal'. */
    #[Url(as: 'type')]
    public string $ticketType = 'client';

    public function mount(): void
    {
        Gate::authorize('tickets.view');

        if (! in_array($this->ticketType, ['client', 'internal'], true)) {
            $this->ticketType = 'client';
        }
    }

    public function setType(string $type): void
    {
        $this->ticketType = $type === 'internal' ? 'internal' : 'client';
    }

    public function render(SatisfactionStats $stats): View
    {
        $type = TicketType::from($this->ticketType);

        $recent = SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->where('type', $type->value)
            ->with(['ticket', 'client.user', 'user'])
            ->orderByDesc('responded_at')
            ->limit(10)
            ->get();

        return view('livewire.admin.satisfaction.satisfaction-index', [
            'ticketType' => $this->ticketType,
            'distribution' => $stats->distribution($type),
            'average' => $stats->average($type),
            'answered' => $stats->answered($type),
            'pending' => $stats->pending($type),
            'questionBreakdown' => $stats->questionBreakdown($type),
            'recent' => $recent,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
