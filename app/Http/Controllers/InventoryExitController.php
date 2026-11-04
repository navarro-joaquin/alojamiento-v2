<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InventoryExitController extends Controller
{
    /**
     * An exit (loss, breakage, return to supplier) or an internal consumption.
     */
    public function store(Request $request, Warehouse $warehouse, InventoryService $inventoryService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $warehouse->branch_id);
        Gate::authorize('registrar', [InventoryMovement::class, $this->sucursalActiva($request)]);

        $validated = $request->validate([
            'exit_movement_type' => ['required', Rule::in(array_map(fn (InventoryMovementType $type): string => $type->value, InventoryMovementType::salidasManuales()))],
            'exit_product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
            'exit_quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:9999999'],
            'exit_notes' => ['required', 'string', 'max:500'],
            'exit_operation_uuid' => ['required', 'uuid'],
        ], attributes: [
            'exit_movement_type' => 'tipo de salida',
            'exit_product_id' => 'producto',
            'exit_quantity' => 'cantidad',
            'exit_notes' => 'motivo',
        ]);

        $movement = $inventoryService->registrarSalida(
            $warehouse,
            Product::findOrFail($validated['exit_product_id']),
            $request->user(),
            InventoryMovementType::from($validated['exit_movement_type']),
            (string) $validated['exit_quantity'],
            $validated['exit_operation_uuid'],
            notes: $validated['exit_notes'],
        );

        return redirect()->route('inventory.index', ['almacen' => $warehouse->id])
            ->with('status', 'Se registró: '.mb_strtolower($movement->movement_type->etiqueta())." de {$movement->quantity}. Quedan {$movement->balance_after}.");
    }
}
