<?php

namespace App\Livewire\Admin\Plans;

use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Plan;
use App\Domain\Services\Services\PlanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PlanIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Plan::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $planId, PlanService $plans): void
    {
        Gate::authorize('delete', Plan::class);

        $plan = Plan::findOrFail($planId);
        $plans->delete($plan);

        session()->flash('status', 'Plan eliminado.');
    }

    public function render(): View
    {
        $plans = Plan::query()
            ->with('prices')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.plans.plan-index', [
            'plans' => $plans,
            'typeOptions' => ServiceType::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
