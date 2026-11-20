<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

/** Every role may list loans; Loan::visibleTo() limits employees to their own rows. */
class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function return(User $user, Loan $loan): bool
    {
        return $user->canManageInventory();
    }
}
