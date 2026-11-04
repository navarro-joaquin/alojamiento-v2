<x-layouts.app title="Almacenes">
    <div>
        <a href="{{ route('warehouses.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nuevo almacén</a>
    </div>

    <x-card>
        @if ($warehouses->isEmpty())
            <p class="text-sm text-gray-600">La sucursal todavía no tiene almacenes.</p>
        @else
            <ul class="flex flex-col divide-y divide-gray-100 text-sm">
                @foreach ($warehouses as $warehouse)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span class="flex items-center gap-2">
                            <span class="font-medium">{{ $warehouse->name }}</span>
                            @unless ($warehouse->is_active)
                                <x-badge>Inactivo</x-badge>
                            @endunless
                        </span>
                        <a href="{{ route('warehouses.edit', $warehouse) }}" class="underline">Editar</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
    <p class="text-sm text-gray-600">Cada almacén lleva sus propias existencias (por ejemplo, el depósito central y el minibar o la tienda de recepción). Un almacén inactivo ya no admite movimientos nuevos.</p>
</x-layouts.app>
