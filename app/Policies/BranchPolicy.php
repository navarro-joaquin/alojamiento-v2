<?php

namespace App\Policies;

use App\Enums\BranchPermission;
use App\Models\Branch;
use App\Models\User;

/**
 * Branches are global: only a super admin manages them, and Gate::before
 * grants every ability to a super admin. Reports and the audit log are read
 * per branch.
 */
class BranchPolicy
{
    public function gestionar(User $user): bool
    {
        return false;
    }

    public function verReportes(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ViewReports, $branch->id);
    }

    public function verAuditoria(User $user, Branch $branch): bool
    {
        return $user->tienePermisoEnSucursal(BranchPermission::ViewAuditLog, $branch->id);
    }
}
