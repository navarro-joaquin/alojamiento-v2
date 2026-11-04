<?php

namespace App\Policies;

use App\Models\User;

/**
 * The product catalog is shared by every branch, so stock and prices are
 * comparable: only a super admin manages it, and Gate::before grants every
 * ability to a super admin.
 */
class ProductPolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }
}
