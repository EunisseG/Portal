<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Users\Services\UserService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserIndex extends Component
{
    use WithPagination;

    /** Pestaña activa: 'internal' (equipo) o 'clients'. */
    #[Url(as: 'tab')]
    public string $tab = 'internal';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'clients' ? 'clients' : 'internal';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function toggleBlock(int $userId, UserService $users): void
    {
        $user = User::findOrFail($userId);

        Gate::authorize('block', $user);

        if ($user->isBlocked()) {
            $users->unblock($user);
            session()->flash('status', 'Usuario desbloqueado.');
        } else {
            $users->block($user);
            session()->flash('status', 'Usuario bloqueado.');
        }
    }

    public function render(): View
    {
        $internalRoles = array_map(fn (RoleName $r) => $r->value, RoleName::adminRoles());

        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            // Filtro por pestaña: internos (equipo) vs clientes.
            ->when($this->tab === 'clients',
                fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', RoleName::Client->value)),
                fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', $internalRoles)),
            )
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.users.user-index', [
            'users' => $users,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
