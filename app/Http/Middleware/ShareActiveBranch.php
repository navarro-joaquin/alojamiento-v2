<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * For screens that do not require an active branch (global catalogs, branch
 * selection): if the user already chose one of their branches, share it with
 * the views so the menu keeps its branch links. Unlike EnsureActiveBranch it
 * never redirects nor changes the permissions team.
 */
class ShareActiveBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $activeBranchId = $request->session()->get(EnsureActiveBranch::SESSION_KEY);

        if ($activeBranchId !== null) {
            $activeBranch = $request->user()->sucursalesAsignadas()->firstWhere('id', $activeBranchId);

            if ($activeBranch !== null) {
                View::share('activeBranch', $activeBranch);
            }
        }

        return $next($request);
    }
}
