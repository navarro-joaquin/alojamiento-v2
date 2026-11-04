<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransfer;
use App\Services\InventoryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CancelledInventoryTransferController extends Controller
{
    public function store(Request $request, InventoryTransfer $inventoryTransfer, InventoryTransferService $inventoryTransferService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $inventoryTransfer->fromWarehouse->branch_id);
        Gate::authorize('cancelar', $inventoryTransfer);

        $inventoryTransferService->cancelar($inventoryTransfer, $request->user());

        return redirect()->route('inventory-transfers.show', $inventoryTransfer)->with('status', 'Transferencia cancelada.');
    }
}
