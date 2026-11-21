<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

/** "Still used by assets" on delete is a 409 from the Service, not a Policy denial. */
class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }

    public function restore(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }

    /** Only for a location nothing references; the controller checks that (409). */
    public function forceDelete(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }
}
