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

class InventoryEntryController extends Controller
{
    /**
     * The form carries the operation_uuid generated when it was rendered, so a
     * double submission records a single entry.
     */
    public function store(Request $request, Warehouse $warehouse, InventoryService $inventoryService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $warehouse->branch_id);
        Gate::authorize('registrar', [InventoryMovement::class, $this->sucursalActiva($request)]);

        $validated = $request->validate([
            'entry_product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
            'entry_quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:9999999'],
            'entry_unit_cost' => ['required', 'decimal:0,4', 'min:0', 'max:9999999'],
            'entry_reference' => ['nullable', 'string', 'max:100'],
            'entry_notes' => ['nullable', 'string', 'max:500'],
            'entry_operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'entry_product_id' => 'producto',
            'entry_quantity' => 'cantidad',
            'entry_unit_cost' => 'costo unitario',
            'entry_reference' => 'referencia',
            'entry_notes' => 'observaciones',
        ]);

        $movement = $inventoryService->registrarEntrada(
            $warehouse,
            Product::findOrFail($validated['entry_product_id']),
            $request->user(),
            (string) $validated['entry_quantity'],
            (string) $validated['entry_unit_cost'],
            $validated['entry_operation_uuid'],
            $validated['entry_reference'] ?? null,
            $validated['entry_notes'] ?? null,
        );

        return redirect()->route('inventory.index', ['almacen' => $warehouse->id])
            ->with('status', "Entrada de {$movement->quantity} registrada: quedan {$movement->balance_after}.");
    }
}
