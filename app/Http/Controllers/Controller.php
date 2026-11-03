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
}
