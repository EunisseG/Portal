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

class StaffLoginHistory extends Component
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
        // Personal administrativo + empleados internos (los que levantan
        // tickets): ambos son "personal" de la empresa a efectos de accesos.
        $roles = array_map(fn (RoleName $r) => $r->value, RoleName::adminRoles());
        $roles[] = RoleName::Employee->value;

        $logins = Audit::query()
            ->with('user')
            ->where('action', AuditAction::Login->value)
            ->whereHas('user', fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $roles)))
            ->when($this->search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%')->orWhere('email', 'like', '%'.$this->search.'%')))
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.activity.login-history', [
            'logins' => $logins,
            'title' => 'Inicio de sesión del personal',
            'subtitle' => 'Historial de accesos del equipo administrativo.',
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
