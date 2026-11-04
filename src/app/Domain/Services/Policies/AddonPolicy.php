<?php

namespace App\Domain\Services\Policies;

use App\Models\User;

class AddonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('addons.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('addons.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('addons.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('addons.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('addons.manage');
    }
}
