<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryAdjustmentController extends Controller
{
    /**
     * Bring the stock of a product to the quantity counted on the shelf.
     */
    public function store(Request $request, Warehouse $warehouse, InventoryService $inventoryService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $warehouse->branch_id);
        Gate::authorize('ajustar', [InventoryMovement::class, $this->sucursalActiva($request)]);

        $validated = $request->validate([
            'adjustment_product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
            'adjustment_counted_quantity' => ['required', 'decimal:0,3', 'min:0', 'max:9999999'],
            'adjustment_notes' => ['required', 'string', 'max:500'],
            'adjustment_operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'adjustment_product_id' => 'producto',
            'adjustment_counted_quantity' => 'cantidad contada',
            'adjustment_notes' => 'motivo',
        ]);

        $movement = $inventoryService->registrarAjuste(
            $warehouse,
            Product::findOrFail($validated['adjustment_product_id']),
            $request->user(),
            (string) $validated['adjustment_counted_quantity'],
            $validated['adjustment_notes'],
            $validated['adjustment_operation_uuid'],
        );

        return redirect()->route('inventory.index', ['almacen' => $warehouse->id])
            ->with('status', "Ajuste registrado: quedan {$movement->balance_after}.");
    }
}
