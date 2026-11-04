<?php

namespace App\Livewire\Admin\Integrations;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Integrations\Models\IntegrationLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class IntegrationLogIndex extends Component
{
    use WithPagination;

    #[Url(as: 'int')]
    public string $integrationFilter = '';

    #[Url(as: 'estado')]
    public string $successFilter = '';

    public function mount(): void
    {
        Gate::authorize('integrations.manage');
    }

    public function updatingIntegrationFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSuccessFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logs = IntegrationLog::query()
            ->with('integration')
            ->when($this->integrationFilter !== '', fn ($q) => $q->where('integration_id', $this->integrationFilter))
            ->when($this->successFilter !== '', fn ($q) => $q->where('success', $this->successFilter === 'ok'))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.integrations.integration-log-index', [
            'logs' => $logs,
            'integrations' => Integration::orderBy('name')->get(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
