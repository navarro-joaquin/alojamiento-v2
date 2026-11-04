<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log of the active branch, newest first. Global operations
 * (branches, user accounts) have no branch and are not listed here.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verAuditoria', $branch);

        $validated = $request->validate([
            'registro' => ['nullable', 'string', 'max:50'],
            'evento' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $activities = Activity::query()
            ->where('branch_id', $branch->id)
            ->when($validated['registro'] ?? null, fn ($query, string $logName) => $query->where('log_name', $logName))
            ->when($validated['evento'] ?? null, fn ($query, string $event) => $query->where('event', 'like', '%'.addcslashes($event, '%_\\').'%'))
            ->when($validated['desde'] ?? null, fn ($query, string $fromDate) => $query->where('created_at', '>=', CarbonImmutable::parse($fromDate, $branch->timezone)->startOfDay()->utc()))
            ->when($validated['hasta'] ?? null, fn ($query, string $toDate) => $query->where('created_at', '<', CarbonImmutable::parse($toDate, $branch->timezone)->addDay()->startOfDay()->utc()))
            ->with('causer')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit-log.index', [
            'activities' => $activities,
            'logNames' => Activity::query()->where('branch_id', $branch->id)->distinct()->orderBy('log_name')->pluck('log_name'),
        ]);
    }
}
