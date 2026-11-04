<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Products are deactivated rather than deleted, so their movements keep them.
 * A product sold to guests needs a sale price.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('gestionar', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => ['nullable', 'integer', Rule::exists(ProductCategory::class, 'id')],
            'sku' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique(Product::class)->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'sale_price' => ['nullable', 'required_if_accepted:is_sellable', 'decimal:0,2', 'gt:0', 'max:999999'],
            'is_sellable' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_category_id' => 'categoría',
            'sku' => 'código',
            'unit' => 'unidad',
            'sale_price' => 'precio de venta',
            'is_sellable' => 'se vende a huéspedes',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => mb_strtoupper(trim((string) $this->input('sku'))),
            'is_sellable' => $this->boolean('is_sellable'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
