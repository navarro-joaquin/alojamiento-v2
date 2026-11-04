<?php

namespace App\Policies;

use App\Models\User;

/**
 * Expense categories are shared by every branch: only a super admin manages
 * them, and Gate::before grants every ability to a super admin.
 */
class ExpenseCategoryPolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }
}
