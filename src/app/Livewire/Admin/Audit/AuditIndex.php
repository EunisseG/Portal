<?php

namespace App\Livewire\Admin\Audit;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AuditIndex extends Component
{
    use WithPagination;

    #[Url(as: 'action')]
    public string $actionFilter = '';

    public function mount(): void
    {
        Gate::authorize('audit.view');
    }

    public function updatingActionFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $audits = Audit::query()
            ->with('user')
            ->when($this->actionFilter !== '', fn ($q) => $q->where('action', $this->actionFilter))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.audit.audit-index', [
            'audits' => $audits,
            'actionOptions' => AuditAction::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
