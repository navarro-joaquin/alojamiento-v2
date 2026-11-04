<x-layouts.app title="Sucursales">
    <div>
        <a href="{{ route('branches.create') }}" class="inline-flex rounded-lg bg-denom-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-denom-800">Nueva sucursal</a>
    </div>

    <x-card>
        @if ($branches->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay sucursales.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Código</th>
                            <th class="py-2 pr-4 font-semibold">Nombre</th>
                            <th class="py-2 pr-4 font-semibold">Fecha de negocio</th>
                            <th class="py-2 pr-4 text-right font-semibold">Habitaciones</th>
                            <th class="py-2 pr-4 font-semibold">Estado</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
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
