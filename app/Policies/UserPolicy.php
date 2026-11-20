<?php

namespace App\Policies;

use App\Models\User;

/** User management is admin-only. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /** An admin cannot deactivate themselves (would lock the last admin out). */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->isNot($model);
    }
}
