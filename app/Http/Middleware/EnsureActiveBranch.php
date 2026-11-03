<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the branch the user is working in, verified against the roles
 * assigned to the user, and makes it the active permissions team.
 */
class EnsureActiveBranch
{
    public const SESSION_KEY = 'active_branch_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->estaHabilitado()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Su usuario está deshabilitado.']);
        }

        $assignedBranches = $user->sucursalesAsignadas();

        if ($assignedBranches->isEmpty() && $user->is_super_admin) {
            return redirect()->route('branches.create');
        }

        abort_if($assignedBranches->isEmpty(), Response::HTTP_FORBIDDEN, 'No tiene sucursales asignadas.');

        $activeBranch = $assignedBranches->firstWhere('id', $request->session()->get(self::SESSION_KEY))
            ?? ($assignedBranches->count() === 1 ? $assignedBranches->first() : null);

        if ($activeBranch === null) {
            return redirect()->route('active-branch.edit');
        }

        $this->activar($request, $activeBranch);

        return $next($request);
    }

    private function activar(Request $request, Branch $branch): void
    {
        $request->session()->put(self::SESSION_KEY, $branch->id);
        $request->attributes->set('activeBranch', $branch);
        setPermissionsTeamId($branch->id);
        Context::add('branch_id', $branch->id);
        View::share('activeBranch', $branch);
    }
}
