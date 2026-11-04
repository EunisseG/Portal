<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AddonIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'estado')]
    public string $statusFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Addon::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $addons = Addon::query()
            ->with(['service.client.user'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('service', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.services.addon-index', [
            'addons' => $addons,
            'statusOptions' => AddonStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
