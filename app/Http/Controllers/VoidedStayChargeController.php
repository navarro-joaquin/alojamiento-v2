<?php

namespace App\Http\Controllers;

use App\Models\StayCharge;
use App\Services\ChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VoidedStayChargeController extends Controller
{
    public function store(Request $request, StayCharge $stayCharge, ChargeService $chargeService): RedirectResponse
    {
        Gate::authorize('anular', $stayCharge);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        $chargeService->anularCargo($stayCharge, $request->user(), $validated['void_reason']);

        return redirect()->route('stays.show', $stayCharge->stay_id)->with('status', 'Cargo anulado.');
    }
}
