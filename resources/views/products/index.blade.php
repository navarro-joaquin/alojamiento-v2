<x-layouts.app title="Productos">
    <div>
        <a href="{{ route('products.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nuevo producto</a>
    </div>

    <x-card>
        @if ($products->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay productos.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Código</th>
                            <th class="py-2 pr-4 font-medium">Nombre</th>
                            <th class="py-2 pr-4 font-medium">Categoría</th>
                            <th class="py-2 pr-4 font-medium">Unidad</th>
                            <th class="py-2 pr-4 text-right font-medium">Precio de venta</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($products as $product)
                            <tr>
                                <td class="py-2 pr-4 font-mono">{{ $product->sku }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $product->name }}</td>
                                <td class="py-2 pr-4 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $product->unit }}</td>
                                <td class="py-2 pr-4 text-right">{{ $product->is_sellable ? 'Bs '.$product->sale_price : 'No se vende' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($product->is_active)
                                        <x-badge color="green">Activo</x-badge>
                                    @else
                                        <x-badge>Inactivo</x-badge>
                                    @endif
                                </td>
                                <td class="py-2 text-right"><a href="{{ route('products.edit', $product) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="text-sm text-gray-600">El catálogo de productos se comparte entre sucursales; cada sucursal lleva sus existencias en sus almacenes. Un producto inactivo ya no admite movimientos nuevos, pero conserva su historial.</p>
</x-layouts.app>
