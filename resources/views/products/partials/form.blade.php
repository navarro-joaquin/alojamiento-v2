{{-- Expects: $product, $categoryOptions --}}
<x-card>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-field label="Código (SKU)" name="sku" :value="$product->sku" required />
        <x-field label="Nombre" name="name" :value="$product->name" required />
        <x-select label="Categoría" name="product_category_id" :options="$categoryOptions" :selected="$product->product_category_id" />
        <x-field label="Unidad" name="unit" :value="$product->unit" required />
        <x-field label="Precio de venta (Bs)" name="sale_price" inputmode="decimal" :value="$product->sale_price" />
    </div>
    <div class="flex flex-col gap-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_sellable" value="0">
            <input type="checkbox" name="is_sellable" value="1" @checked(old('is_sellable', $product->is_sellable)) class="rounded border-gray-300">
            Se vende a huéspedes (requiere precio de venta)
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-gray-300">
            Producto activo
        </label>
    </div>
</x-card>
