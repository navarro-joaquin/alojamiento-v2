@php($productOptions = ['' => 'Sin producto'] + $products->mapWithKeys(fn ($product) => [$product->id => "{$product->name} ({$product->unit})"])->all())

<x-layouts.app title="Transferencias">
    @if ($incomingTransfers->isNotEmpty())
        <x-card title="Por recibir">
            <ul class="flex flex-col divide-y divide-gray-100 text-sm">
                @foreach ($incomingTransfers as $incomingTransfer)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                        <span>
                            <span class="font-medium">#{{ $incomingTransfer->id }}</span>
                            desde {{ $incomingTransfer->fromWarehouse->branch->name }} · {{ $incomingTransfer->fromWarehouse->name }}
                            hacia {{ $incomingTransfer->toWarehouse->name }}
                            <span class="text-gray-600">({{ $incomingTransfer->items_count }} productos)</span>
                        </span>
                        <a href="{{ route('inventory-transfers.show', $incomingTransfer) }}" class="underline">Recibir</a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    @can('crear', [\App\Models\InventoryTransfer::class, $activeBranch])
        <x-card title="Nueva transferencia">
            @if ($sourceWarehouses->isEmpty())
                <p class="text-sm text-gray-600">La sucursal no tiene almacenes activos.</p>
            @else
                <form method="POST" action="{{ route('inventory-transfers.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select label="Desde" name="from_warehouse_id" :options="$sourceWarehouses->pluck('name', 'id')->all()" />
                        <x-select label="Hacia" name="to_warehouse_id"
                            :options="$destinationWarehouses->mapWithKeys(fn ($warehouse) => [$warehouse->id => $warehouse->branch->name.' · '.$warehouse->name])->all()" />
                        <x-field label="Observaciones" name="notes" />
                    </div>
                    <div class="grid gap-3">
                        @for ($row = 0; $row < 5; $row++)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-select label="Producto {{ $row + 1 }}" name="items[{{ $row }}][product_id]" :options="$productOptions" />
                                <x-field label="Cantidad" name="items[{{ $row }}][quantity]" inputmode="decimal" />
                            </div>
                        @endfor
                    </div>
                    <p class="text-sm text-gray-600">Se crea como borrador y no mueve existencias hasta enviarla. Las filas sin producto se ignoran.</p>
                    <div>
                        <x-button>Crear borrador</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    @endcan

    <x-card title="Transferencias de la sucursal">
        @if ($recentTransfers->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay transferencias.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Número</th>
                            <th class="py-2 pr-4 font-medium">Desde</th>
                            <th class="py-2 pr-4 font-medium">Hacia</th>
                            <th class="py-2 pr-4 text-right font-medium">Productos</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentTransfers as $transfer)
                            <tr>
                                <td class="py-2 pr-4 font-medium">#{{ $transfer->id }}</td>
                                <td class="py-2 pr-4">{{ $transfer->fromWarehouse->branch->name }} · {{ $transfer->fromWarehouse->name }}</td>
                                <td class="py-2 pr-4">{{ $transfer->toWarehouse->branch->name }} · {{ $transfer->toWarehouse->name }}</td>
                                <td class="py-2 pr-4 text-right">{{ $transfer->items_count }}</td>
                                <td class="py-2 pr-4"><x-badge :color="$transfer->status->color()">{{ $transfer->status->etiqueta() }}</x-badge></td>
                                <td class="py-2 text-right"><a href="{{ route('inventory-transfers.show', $transfer) }}" class="underline">Ver</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
