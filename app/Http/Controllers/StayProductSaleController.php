<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stay;
use App\Models\Warehouse;
use App\Services\ChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StayProductSaleController extends Controller
{
    /**
     * Charge a product to the stay at its sale price and take it out of stock.
     */
    public function store(Request $request, Stay $stay, ChargeService $chargeService): RedirectResponse
    {
        Gate::authorize('registrarCargo', $stay);

        $validated = $request->validate([
            'sale_warehouse_id' => ['required', 'integer', Rule::exists(Warehouse::class, 'id')],
            'sale_product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')],
            'sale_quantity' => ['required', 'decimal:0,2', 'gt:0', 'max:9999'],
        ], attributes: [
            'sale_warehouse_id' => 'almacén',
            'sale_product_id' => 'producto',
            'sale_quantity' => 'cantidad',
        ]);

        $stayCharge = $chargeService->venderProducto(
            $stay,
            $request->user(),
            Warehouse::findOrFail($validated['sale_warehouse_id']),
            Product::findOrFail($validated['sale_product_id']),
            (string) $validated['sale_quantity'],
        );

        return redirect()->route('stays.show', $stay)->with('status', "Venta registrada: {$stayCharge->description} por Bs {$stayCharge->total_amount}.");
    }
}
