<x-layouts.app title="Sucursales">
    <div>
        <a href="{{ route('branches.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nueva sucursal</a>
    </div>

    <x-card>
        @if ($branches->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay sucursales.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Código</th>
                            <th class="py-2 pr-4 font-medium">Nombre</th>
                            <th class="py-2 pr-4 font-medium">Fecha de negocio</th>
                            <th class="py-2 pr-4 text-right font-medium">Habitaciones</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($branches as $branch)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $branch->code }}</td>
                                <td class="py-2 pr-4">{{ $branch->name }}</td>
                                <td class="py-2 pr-4">{{ $branch->current_business_date->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4 text-right">{{ $branch->rooms_count }}</td>
                                <td class="py-2 pr-4"><x-badge :color="$branch->is_active ? 'green' : 'gray'">{{ $branch->is_active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                                <td class="py-2 text-right"><a href="{{ route('branches.edit', $branch) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
