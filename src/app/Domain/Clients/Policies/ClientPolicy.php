<?php

namespace App\Domain\Clients\Policies;

use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('clients.manage');
    }

    /** Gestionar (ajustar) el saldo a favor del cliente. */
    public function manageCredit(User $user): bool
    {
        return $user->can('client_credit.manage');
    }
}
