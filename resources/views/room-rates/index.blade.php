<x-layouts.app title="Tarifas">
    <div>
        <a href="{{ route('room-rates.create') }}" class="inline-flex rounded-lg bg-denom-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-denom-800">Nueva tarifa</a>
    </div>

    <x-card>
        @if ($roomRates->isEmpty())
            <p class="text-sm text-gray-600">La sucursal todavía no tiene tarifas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Tipo</th>
                            <th class="py-2 pr-4 font-semibold">Nombre</th>
                            <th class="py-2 pr-4 text-right font-semibold">Importe</th>
                            <th class="py-2 pr-4 font-semibold">Huéspedes</th>
                            <th class="py-2 pr-4 font-semibold">Días</th>
                            <th class="py-2 pr-4 text-right font-semibold">Prioridad</th>
                            <th class="py-2 pr-4 font-semibold">Vigencia</th>
                            <th class="py-2 pr-4 font-semibold">Estado</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($roomRates as $roomRate)
                            <tr>
                                <td class="py-2 pr-4">{{ $roomRate->roomType->name }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $roomRate->name }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $roomRate->amount }}</td>
                                <td class="py-2 pr-4">{{ $roomRate->occupancy ?? 'Cualquiera' }}</td>
                                <td class="py-2 pr-4">
                                    {{ $roomRate->days_of_week === null ? 'Todos' : collect($roomRate->days_of_week)->map(fn (int $day) => \Illuminate\Support\Carbon::create(2024, 1, $day)->locale('es')->minDayName)->join(', ') }}
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $roomRate->priority }}</td>
                                <td class="py-2 pr-4">{{ $roomRate->valid_from->format('d/m/Y') }} – {{ $roomRate->valid_to?->format('d/m/Y') ?? 'sin fin' }}</td>
                                <td class="py-2 pr-4"><x-badge :color="$roomRate->is_active ? 'green' : 'gray'">{{ $roomRate->is_active ? 'Activa' : 'Inactiva' }}</x-badge></td>
                                <td class="py-2 text-right"><a href="{{ route('room-rates.edit', $roomRate) }}" class="underline">Editar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
