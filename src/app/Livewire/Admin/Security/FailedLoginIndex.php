<?php

namespace App\Livewire\Admin\Security;

use App\Domain\Auth\Models\FailedLoginAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FailedLoginIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('security.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $attempts = FailedLoginAttempt::query()
            ->when($this->search !== '', fn ($q) => $q->where('email', 'like', '%'.$this->search.'%'))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.security.failed-login-index', [
            'attempts' => $attempts,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
