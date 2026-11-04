<?php

namespace App\Domain\Services\Policies;

use App\Models\User;

class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('domains.view');
    }

    public function view(User $user): bool
    {
        return $user->can('domains.view');
    }

    public function create(User $user): bool
    {
        return $user->can('domains.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('domains.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('domains.manage');
    }
}
