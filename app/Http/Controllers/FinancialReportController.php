<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Financial report of the active branch over a range of business dates,
 * by default the current one.
 */
class FinancialReportController extends Controller
{
    public function index(Request $request, FinancialReportService $financialReportService): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verReportes', $branch);

        $validated = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $currentBusinessDate = $branch->current_business_date->toImmutable();
        $fromDate = isset($validated['desde']) ? CarbonImmutable::parse($validated['desde']) : $currentBusinessDate;
        $toDate = isset($validated['hasta']) ? CarbonImmutable::parse($validated['hasta']) : $fromDate;

        if ($fromDate->diffInDays($toDate) > 92) {
            $toDate = $fromDate->addDays(92);
        }

        return view('reports.index', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'report' => $financialReportService->resumir($branch, $fromDate, $toDate),
            'outstandingStays' => $financialReportService->listarSaldosPendientes($branch),
        ]);
    }
}
