<?php

namespace App\Domain\Content\Policies;

use App\Models\User;

/**
 * Política compartida para categorías y artículos de la base de conocimientos.
 */
class KbPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('kb.view');
    }

    public function create(User $user): bool
    {
        return $user->can('kb.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('kb.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('kb.manage');
    }
}
