<?php

namespace App\Http\Controllers;

use App\Enums\BranchRole;
use App\Http\Requests\StaffMemberRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The staff of the active branch: accounts holding a role there (the
 * active branch is the permissions team, so `roles` is already scoped to
 * it). Each member holds a single role per branch.
 */
class StaffMemberController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('gestionarPersonal', [User::class, $this->sucursalActiva($request)]);

        return view('staff-members.index', [
            'staffMembers' => User::query()->whereHas('roles')->with('roles')->orderBy('name')->get(),
            'roles' => collect(BranchRole::cases())->mapWithKeys(fn (BranchRole $role): array => [$role->value => $role->etiqueta()]),
        ]);
    }

    public function store(StaffMemberRequest $request, AuditService $auditService): RedirectResponse
    {
        $branch = $this->sucursalActiva($request);
        $staffMember = $request->miembro();
        $previousRoles = $staffMember->rolesEnSucursal($branch->id);

        $staffMember->cambiarRolEnSucursal($request->rol(), $branch->id);

        $auditService->registrar('security', 'staff.role_assigned', $staffMember, $request->user(), $branch->id, [
            'role' => $request->rol()->value,
            'previous_roles' => $previousRoles,
        ]);

        return redirect()->route('staff-members.index')->with('status', "{$staffMember->name} es {$request->rol()->etiqueta()} en la sucursal.");
    }

    public function destroy(Request $request, User $user, AuditService $auditService): RedirectResponse
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('gestionarPersonal', [User::class, $branch]);

        $previousRoles = $user->rolesEnSucursal($branch->id);
        abort_if($previousRoles === [], 404);

        if ($user->is($request->user())) {
            return back()->withErrors(['operation' => 'No puede quitarse a sí mismo de la sucursal.']);
        }

        $user->quitarRolesEnSucursal($branch->id);

        $auditService->registrar('security', 'staff.roles_removed', $user, $request->user(), $branch->id, ['previous_roles' => $previousRoles]);

        return redirect()->route('staff-members.index')->with('status', "{$user->name} ya no forma parte del personal de la sucursal.");
    }
}
