<x-layouts.app title="Habitaciones">
    <div>
        <a href="{{ route('rooms.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nueva habitación</a>
    </div>

    <x-card>
        @if ($rooms->isEmpty())
            <p class="text-sm text-gray-600">La sucursal todavía no tiene habitaciones.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Número</th>
                            <th class="py-2 pr-4 font-medium">Piso</th>
                            <th class="py-2 pr-4 font-medium">Tipo</th>
                            <th class="py-2 pr-4 text-right font-medium">Capacidad</th>
                            <th class="py-2 pr-4 font-medium">Estado operativo</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rooms as $room)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $room->room_number }}</td>
                                <td class="py-2 pr-4">{{ $room->floor }}</td>
                                <td class="py-2 pr-4">{{ $room->roomType->name }}</td>
                                <td class="py-2 pr-4 text-right">{{ $room->capacity }}</td>
                                <td class="py-2 pr-4">{{ $room->operational_status->etiqueta() }}</td>
                                <td class="py-2 text-right"><a href="{{ route('rooms.edit', $room) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
