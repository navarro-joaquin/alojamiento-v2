<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('gestionar', ProductCategory::class);

        return view('product-categories.index', [
            'productCategories' => ProductCategory::query()->withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gestionar', ProductCategory::class);

        return view('product-categories.create', ['productCategory' => new ProductCategory]);
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $productCategory = ProductCategory::create($request->validated());

        return redirect()->route('product-categories.index')->with('status', "Categoría {$productCategory->name} creada.");
    }

    public function edit(ProductCategory $productCategory): View
    {
        Gate::authorize('gestionar', ProductCategory::class);

        return view('product-categories.edit', ['productCategory' => $productCategory]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update($request->validated());

        return redirect()->route('product-categories.index')->with('status', "Categoría {$productCategory->name} actualizada.");
    }
}
