<x-layouts.app title="Tipos de habitación">
    <div>
        <a href="{{ route('room-types.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nuevo tipo</a>
    </div>

    <x-card>
        @if ($roomTypes->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay tipos de habitación.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Nombre</th>
                            <th class="py-2 pr-4 font-medium">Descripción</th>
                            <th class="py-2 pr-4 text-right font-medium">Capacidad habitual</th>
                            <th class="py-2 pr-4 text-right font-medium">Habitaciones</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($roomTypes as $roomType)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $roomType->name }}</td>
                                <td class="py-2 pr-4 text-gray-600">{{ $roomType->description }}</td>
                                <td class="py-2 pr-4 text-right">{{ $roomType->default_capacity }}</td>
                                <td class="py-2 pr-4 text-right">{{ $roomType->rooms_count }}</td>
                                <td class="py-2 text-right"><a href="{{ route('room-types.edit', $roomType) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
