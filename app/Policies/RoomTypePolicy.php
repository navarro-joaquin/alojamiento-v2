<?php

namespace App\Policies;

use App\Models\User;

/**
 * Room types are shared by every branch: only a super admin manages them,
 * and Gate::before grants every ability to a super admin.
 */
class RoomTypePolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }
}
