<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransfer;
use App\Services\InventoryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryTransferReceptionController extends Controller
{
    /**
     * Receive a transfer in transit: the destination branch counts what arrived.
     */
    public function store(Request $request, InventoryTransfer $inventoryTransfer, InventoryTransferService $inventoryTransferService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $inventoryTransfer->toWarehouse->branch_id);
        Gate::authorize('recibir', $inventoryTransfer);

        $validated = $request->validate([
            'received' => ['required', 'array'],
            'received.*' => ['required', 'decimal:0,3', 'min:0', 'max:9999999'],
            'reception_notes' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'received.*' => 'cantidad recibida',
            'reception_notes' => 'motivo del faltante',
        ]);

        $inventoryTransferService->recibir(
            $inventoryTransfer,
            $request->user(),
            array_map(fn (mixed $quantity): string => (string) $quantity, $validated['received']),
            $validated['reception_notes'] ?? null,
        );

        return redirect()->route('inventory-transfers.show', $inventoryTransfer)->with('status', 'Transferencia recibida.');
    }
}
