<?php

namespace App\Http\Controllers;

use App\Enums\InventoryTransferStatus;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    /**
     * Transfers that leave from or arrive at a warehouse of the active branch.
     */
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('verTransferencias', [InventoryTransfer::class, $branch]);

        $transfers = InventoryTransfer::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('fromWarehouse', fn (Builder $query) => $query->whereBelongsTo($branch))
                ->orWhereHas('toWarehouse', fn (Builder $query) => $query->whereBelongsTo($branch)))
            ->with(['fromWarehouse.branch', 'toWarehouse.branch', 'createdByUser'])
            ->withCount('items')
            ->latest('id');

        return view('inventory-transfers.index', [
            'incomingTransfers' => (clone $transfers)
                ->where('status', InventoryTransferStatus::Sent)
                ->whereHas('toWarehouse', fn (Builder $query) => $query->whereBelongsTo($branch))
                ->get(),
            'recentTransfers' => (clone $transfers)->limit(50)->get(),
            'sourceWarehouses' => $branch->warehouses()->activos()->orderBy('name')->get(),
            'destinationWarehouses' => Warehouse::query()->activos()->with('branch')->get()
                ->sortBy(fn (Warehouse $warehouse): string => ($warehouse->branch_id === $branch->id ? '0' : '1').$warehouse->branch->name.$warehouse->name),
            'products' => Product::query()->activos()->orderBy('name')->get(),
        ]);
    }

    /**
     * Rows of the form without a product are ignored.
     */
    public function store(Request $request, InventoryTransferService $inventoryTransferService): RedirectResponse
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('crear', [InventoryTransfer::class, $branch]);

        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer', Rule::exists(Warehouse::class, 'id')->where('branch_id', $branch->id)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists(Warehouse::class, 'id')],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer', 'distinct', Rule::exists(Product::class, 'id')],
            'items.*.quantity' => ['nullable', 'required_with:items.*.product_id', 'decimal:0,3', 'gt:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'from_warehouse_id' => 'almacén de origen',
            'to_warehouse_id' => 'almacén de destino',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity' => 'cantidad',
            'notes' => 'observaciones',
        ]);

        $quantitiesByProduct = collect($validated['items'])
            ->filter(fn (array $item): bool => filled($item['product_id'] ?? null))
            ->mapWithKeys(fn (array $item): array => [(int) $item['product_id'] => (string) $item['quantity']])
            ->all();

        $inventoryTransfer = $inventoryTransferService->crear(
            Warehouse::findOrFail($validated['from_warehouse_id']),
            Warehouse::findOrFail($validated['to_warehouse_id']),
            $request->user(),
            $quantitiesByProduct,
            $validated['notes'] ?? null,
        );

        return redirect()->route('inventory-transfers.show', $inventoryTransfer)
            ->with('status', "Transferencia #{$inventoryTransfer->id} creada como borrador: revísela y envíela.");
    }

    public function show(Request $request, InventoryTransfer $inventoryTransfer): View
    {
        $branch = $this->sucursalActiva($request);
        $inventoryTransfer->load(['fromWarehouse.branch', 'toWarehouse.branch', 'items.product', 'createdByUser', 'sentByUser', 'receivedByUser', 'cancelledByUser']);

        abort_unless($inventoryTransfer->involucraSucursal($branch->id), 404);
        Gate::authorize('verTransferencias', [InventoryTransfer::class, $branch]);

        return view('inventory-transfers.show', ['inventoryTransfer' => $inventoryTransfer]);
    }
}
