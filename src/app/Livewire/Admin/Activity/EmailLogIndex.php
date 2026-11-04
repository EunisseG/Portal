<?php

namespace App\Livewire\Admin\Activity;

use App\Domain\Notifications\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmailLogIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $selectedId = null;

    public function mount(): void
    {
        Gate::authorize('audit.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function show(int $id): void
    {
        $this->selectedId = $id;
    }

    public function closeDetail(): void
    {
        $this->selectedId = null;
    }

    public function delete(int $id): void
    {
        Gate::authorize('audit.view');

        EmailLog::whereKey($id)->delete();

        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }

        session()->flash('status', 'Registro de correo eliminado.');
    }

    public function render(): View
    {
        $logs = EmailLog::query()
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('recipient', 'like', '%'.$this->search.'%')
                        ->orWhere('subject', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20);

        $selected = $this->selectedId ? EmailLog::find($this->selectedId) : null;

        return view('livewire.admin.activity.email-log-index', [
            'logs' => $logs,
            'selected' => $selected,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
