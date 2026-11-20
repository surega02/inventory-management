<?php

namespace App\Policies;

use App\Models\Maintenance;
use App\Models\User;

class MaintenancePolicy
{
    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function complete(User $user, Maintenance $maintenance): bool
    {
        return $user->canManageInventory();
    }
}
