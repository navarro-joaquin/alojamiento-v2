<x-layouts.app title="Categorías de producto">
    <div>
        <a href="{{ route('product-categories.create') }}" class="inline-flex rounded-lg bg-denom-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-denom-800">Nueva categoría</a>
    </div>

    <x-card>
        @if ($productCategories->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay categorías de producto.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Nombre</th>
                            <th class="py-2 pr-4 font-semibold">Descripción</th>
                            <th class="py-2 pr-4 text-right font-semibold">Productos</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($productCategories as $productCategory)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $productCategory->name }}</td>
                                <td class="py-2 pr-4 text-gray-600">{{ $productCategory->description }}</td>
                                <td class="py-2 pr-4 text-right">{{ $productCategory->products_count }}</td>
                                <td class="py-2 text-right"><a href="{{ route('product-categories.edit', $productCategory) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="text-sm text-gray-600">Las categorías se comparten entre sucursales y solo sirven para agrupar los productos.</p>
</x-layouts.app>
