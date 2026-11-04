<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransfer;
use App\Services\InventoryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryTransferShipmentController extends Controller
{
    /**
     * Send a draft: the source branch takes the stock out.
     */
    public function store(Request $request, InventoryTransfer $inventoryTransfer, InventoryTransferService $inventoryTransferService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $inventoryTransfer->fromWarehouse->branch_id);
        Gate::authorize('enviar', $inventoryTransfer);

        $inventoryTransferService->enviar($inventoryTransfer, $request->user());

        return redirect()->route('inventory-transfers.show', $inventoryTransfer)->with('status', 'Transferencia enviada: queda en tránsito hasta que la reciba el destino.');
    }
}
