<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Role/action checks only; status conflicts (409) and "has history" checks live in the Service. */
class AssetPolicy
{
    /** The /assets list page; employees reach their assets through /loans instead. */
    public function viewAny(User $user): bool
    {
        return $user->canManageInventory();
    }

    /** Outside the employee's scope answers 404, so they cannot tell the asset exists. */
    public function view(User $user, Asset $asset): Response
    {
        return Asset::visibleTo($user)->whereKey($asset->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->canManageInventory();
    }

    public function dispose(User $user, Asset $asset): bool
    {
        return $user->canManageInventory();
    }

    public function move(User $user, Asset $asset): bool
    {
        return $user->canManageInventory();
    }

    /** Soft delete is admin-only (and only without transaction history, checked in the Service). */
    public function delete(User $user, Asset $asset): bool
    {
        return $user->isAdmin();
    }
}
