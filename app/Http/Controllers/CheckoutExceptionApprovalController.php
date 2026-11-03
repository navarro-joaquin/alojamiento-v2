<?php

namespace App\Http\Controllers;

use App\Models\CheckoutException;
use App\Services\CheckoutExceptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CheckoutExceptionApprovalController extends Controller
{
    public function store(Request $request, CheckoutException $checkoutException, CheckoutExceptionService $checkoutExceptionService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $checkoutException->stay->branch_id);
        Gate::authorize('aprobar', $checkoutException);

        $validated = $request->validate([
            'approval_notes' => ['nullable', 'string', 'max:500'],
        ], attributes: ['approval_notes' => 'observaciones']);

        $approvedException = $checkoutExceptionService->aprobar($checkoutException, $request->user(), $validated['approval_notes'] ?? null);

        return back()->with('status', "Salida con deuda de Bs {$approvedException->outstanding_amount} autorizada hasta las {$approvedException->expires_at->timezone($this->sucursalActiva($request)->timezone)->format('H:i')}.");
    }
}
