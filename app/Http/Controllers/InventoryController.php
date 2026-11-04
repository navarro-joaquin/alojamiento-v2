<?php

namespace App\Http\Controllers;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    /**
     * Stock and latest movements of one active warehouse of the branch,
     * chosen with ?almacen= (the first one by default).
     */
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verInventario', [InventoryMovement::class, $branch]);

        $warehouses = $branch->warehouses()->activos()->orderBy('name')->get();
        $warehouse = $warehouses->firstWhere('id', $request->integer('almacen')) ?? $warehouses->first();

        return view('inventory.index', [
            'warehouses' => $warehouses,
            'warehouse' => $warehouse,
            'balances' => $warehouse?->inventoryBalances()
                ->with('product')
                ->join('products', 'products.id', '=', 'inventory_balances.product_id')
                ->orderBy('products.name')
                ->select('inventory_balances.*')
                ->get() ?? collect(),
            'movements' => $warehouse?->inventoryMovements()
                ->with(['product', 'performedByUser'])
                ->latest('id')
                ->limit(30)
                ->get() ?? collect(),
            'products' => Product::query()->activos()->orderBy('name')->get(),
            'exitTypes' => InventoryMovementType::salidasManuales(),
        ]);
    }
}
