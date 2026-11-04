<?php

namespace App\Livewire\Admin\Activity;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\Audit;
use App\Domain\Auth\Enums\RoleName;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ClientLoginHistory extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('audit.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $logins = Audit::query()
            ->with('user')
            ->where('action', AuditAction::Login->value)
            ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', RoleName::Client->value)))
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.activity.login-history', [
            'logins' => $logins,
            'title' => 'Inicio de sesión del cliente',
            'subtitle' => 'Historial de accesos de los clientes al portal.',
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
