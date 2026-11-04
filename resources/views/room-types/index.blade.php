<x-layouts.app title="Tipos de habitación">
    <div>
        <a href="{{ route('room-types.create') }}" class="inline-flex rounded-lg bg-denom-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-denom-800">Nuevo tipo</a>
    </div>

    <x-card>
        @if ($roomTypes->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay tipos de habitación.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Nombre</th>
                            <th class="py-2 pr-4 font-semibold">Descripción</th>
                            <th class="py-2 pr-4 text-right font-semibold">Capacidad habitual</th>
                            <th class="py-2 pr-4 text-right font-semibold">Habitaciones</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
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
