<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', Product::class);

        return view('products.index', [
            'products' => Product::query()->with('category')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', Product::class);

        return view('products.create', [
            'product' => new Product(['unit' => 'unidad', 'is_active' => true]),
            'categoryOptions' => $this->opcionesDeCategoria(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        return redirect()->route('products.index')->with('status', "Producto {$product->name} creado.");
    }

    public function edit(Product $product): View
    {
        Gate::authorize('gestionar', Product::class);

        return view('products.edit', [
            'product' => $product,
            'categoryOptions' => $this->opcionesDeCategoria(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')->with('status', "Producto {$product->name} actualizado.");
    }

    /**
     * @return array<int|string, string>
     */
    private function opcionesDeCategoria(): array
    {
        return ['' => 'Sin categoría'] + ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
