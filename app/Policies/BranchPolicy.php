<?php

namespace App\Policies;

use App\Models\User;

/**
 * Branches are global: only a super admin manages them, and Gate::before
 * grants every ability to a super admin.
 */
class BranchPolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }
}
