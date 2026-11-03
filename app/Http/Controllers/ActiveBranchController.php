<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureActiveBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActiveBranchController extends Controller
{
    public function edit(Request $request): View
    {
        return view('active-branch.edit', [
            'branches' => $request->user()->sucursalesAsignadas(),
            'activeBranchId' => $request->session()->get(EnsureActiveBranch::SESSION_KEY),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($request->user()->sucursalesAsignadas()->modelKeys())],
        ], [
            'branch_id.in' => 'No tiene acceso a esa sucursal.',
        ]);

        $request->session()->put(EnsureActiveBranch::SESSION_KEY, (int) $validated['branch_id']);

        return redirect()->route('occupancy.index');
    }
}
