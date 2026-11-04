<?php

namespace App\Domain\Users\Policies;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user): bool
    {
        return $user->can('users.update');
    }

    /**
     * Reglas de bloqueo:
     * - Requiere permiso users.block.
     * - Nadie puede bloquearse a sí mismo.
     * - Nadie puede bloquear a un super_admin.
     */
    public function block(User $user, User $target): bool
    {
        if (! $user->can('users.block')) {
            return false;
        }

        if ($user->is($target)) {
            return false;
        }

        if ($target->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        return true;
    }
}
