<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', Branch::class);

        return view('branches.index', [
            'branches' => Branch::query()->withCount('rooms')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', Branch::class);

        return view('branches.create', ['branch' => new Branch(['timezone' => 'America/La_Paz', 'check_in_time' => '14:00', 'check_out_time' => '12:00', 'is_active' => true])]);
    }

    public function store(BranchRequest $request, AuditService $auditService): RedirectResponse
    {
        $branch = Branch::create([
            ...$request->validated(),
            'current_business_date' => CarbonImmutable::now($request->validated('timezone'))->toDateString(),
        ]);

        $auditService->registrar('security', 'branch.created', $branch, $request->user(), $branch->id, ['code' => $branch->code]);

        return redirect()->route('branches.index')->with('status', "Sucursal {$branch->name} creada.");
    }

    public function edit(Branch $branch): View
    {
        Gate::authorize('gestionar', Branch::class);

        return view('branches.edit', ['branch' => $branch]);
    }

    public function update(BranchRequest $request, Branch $branch, AuditService $auditService): RedirectResponse
    {
        $branch->update($request->validated());

        if ($branch->wasChanged()) {
            $auditService->registrar('security', 'branch.updated', $branch, $request->user(), $branch->id, [
                'changes' => collect($branch->getChanges())->except('updated_at')->all(),
            ]);
        }

        return redirect()->route('branches.index')->with('status', "Sucursal {$branch->name} actualizada.");
    }
}
