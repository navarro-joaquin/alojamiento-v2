<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);

        Gate::authorize('gestionar', [Warehouse::class, $branch]);

        return view('warehouses.index', [
            'warehouses' => $branch->warehouses()->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar', [Warehouse::class, $this->sucursalActiva($request)]);

        return view('warehouses.create', ['warehouse' => new Warehouse(['is_active' => true])]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $warehouse = $this->sucursalActiva($request)->warehouses()->create($request->validated());

        return redirect()->route('warehouses.index')->with('status', "Almacén {$warehouse->name} creado.");
    }

    public function edit(Request $request, Warehouse $warehouse): View
    {
        $this->asegurarDeLaSucursalActiva($request, $warehouse->branch_id);
        Gate::authorize('gestionar', [Warehouse::class, $this->sucursalActiva($request)]);

        return view('warehouses.edit', ['warehouse' => $warehouse]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $warehouse->branch_id);

        $warehouse->update($request->validated());

        return redirect()->route('warehouses.index')->with('status', "Almacén {$warehouse->name} actualizado.");
    }
}
