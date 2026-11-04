<?php

namespace App\Domain\Services\Policies;

use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plans.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('plans.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('plans.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('plans.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('plans.manage');
    }
}
