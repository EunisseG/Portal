<?php

namespace App\Domain\Content\Policies;

use App\Models\User;

class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('news.view');
    }

    public function create(User $user): bool
    {
        return $user->can('news.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('news.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('news.manage');
    }
}
