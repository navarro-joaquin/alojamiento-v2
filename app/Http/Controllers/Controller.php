<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The branch resolved by the EnsureActiveBranch middleware.
     */
    protected function sucursalActiva(Request $request): Branch
    {
        return $request->attributes->get('activeBranch');
    }

    /**
     * Screens work on the active branch only: a record of another branch is
     * reported as missing rather than forbidden.
     */
    protected function asegurarDeLaSucursalActiva(Request $request, int $branchId): void
    {
        abort_unless($branchId === $this->sucursalActiva($request)->id, 404);
    }
}
