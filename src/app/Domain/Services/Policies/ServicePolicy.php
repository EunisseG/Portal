<?php

namespace App\Domain\Services\Policies;

use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('services.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('services.manage');
    }
}
