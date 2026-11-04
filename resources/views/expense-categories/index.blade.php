<x-layouts.app title="Categorías de gasto">
    <div>
        <a href="{{ route('expense-categories.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nueva categoría</a>
    </div>

    <x-card>
        @if ($expenseCategories->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay categorías de gasto.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Nombre</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 pr-4 text-right font-medium">Gastos</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($expenseCategories as $expenseCategory)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $expenseCategory->name }}</td>
                                <td class="py-2 pr-4">
                                    @if ($expenseCategory->is_active)
                                        <x-badge color="green">Activa</x-badge>
                                    @else
                                        <x-badge>Inactiva</x-badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $expenseCategory->expenses_count }}</td>
                                <td class="py-2 text-right"><a href="{{ route('expense-categories.edit', $expenseCategory) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="text-sm text-gray-600">Las categorías se comparten entre sucursales. Una categoría inactiva ya no se ofrece al registrar gastos, pero los gastos anteriores la conservan.</p>
</x-layouts.app>
