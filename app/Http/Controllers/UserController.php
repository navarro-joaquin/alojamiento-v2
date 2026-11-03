<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A disabled account is logged out on its next request by EnsureActiveBranch.
 */
class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', User::class);

        return view('users.index', ['users' => User::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', User::class);

        return view('users.create', ['user' => new User]);
    }

    public function store(UserRequest $request, AuditService $auditService): RedirectResponse
    {
        $user = User::create($request->datosDeCuenta());

        $auditService->registrar('security', 'user.created', $user, $request->user(), null, ['email' => $user->email]);

        return redirect()->route('users.index')->with('status', "Usuario {$user->email} creado.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('gestionar', User::class);

        return view('users.edit', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user, AuditService $auditService): RedirectResponse
    {
        $user->update($request->datosDeCuenta());

        if ($user->wasChanged()) {
            $auditService->registrar('security', 'user.updated', $user, $request->user(), null, [
                'changes' => collect($user->getChanges())->except(['password', 'remember_token', 'updated_at'])->all(),
                'password_changed' => $user->wasChanged('password'),
            ]);
        }

        return redirect()->route('users.index')->with('status', "Usuario {$user->email} actualizado.");
    }
}
