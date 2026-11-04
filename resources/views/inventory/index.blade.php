@php($productOptions = ['' => 'Elija un producto'] + $products->mapWithKeys(fn ($product) => [$product->id => "{$product->name} ({$product->unit})"])->all())

<x-layouts.app title="Inventario">
    @if ($warehouses->isEmpty())
        <x-card>
            <p class="text-sm text-gray-600">La sucursal no tiene almacenes activos.</p>
        </x-card>
    @else
        <nav class="flex flex-wrap gap-2" aria-label="Almacenes">
            @foreach ($warehouses as $warehouseOption)
                <a href="{{ route('inventory.index', ['almacen' => $warehouseOption->id]) }}"
                    @class([
                        'rounded-md px-3 py-1.5 text-sm font-medium',
                        'bg-gray-900 text-white' => $warehouseOption->is($warehouse),
                        'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => ! $warehouseOption->is($warehouse),
                    ])
                    @if ($warehouseOption->is($warehouse)) aria-current="page" @endif>{{ $warehouseOption->name }}</a>
            @endforeach
        </nav>

        <x-card :title="'Existencias en '.$warehouse->name">
            @if ($balances->isEmpty())
                <p class="text-sm text-gray-600">El almacén todavía no tiene movimientos.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Producto</th>
                                <th class="py-2 pr-4 text-right font-medium">Cantidad</th>
                                <th class="py-2 pr-4 text-right font-medium">Costo promedio</th>
                                <th class="py-2 text-right font-medium">Valorizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($balances as $balance)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <span class="font-medium">{{ $balance->product->name }}</span>
                                        <span class="block font-mono text-gray-500">{{ $balance->product->sku }}</span>
                                    </td>
                                    <td class="py-2 pr-4 text-right">{{ $balance->quantity }} {{ $balance->product->unit }}</td>
                                    <td class="py-2 pr-4 text-right">Bs {{ $balance->average_cost }}</td>
                                    <td class="py-2 text-right">Bs {{ $balance->valorizado() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @can('registrar', [\App\Models\InventoryMovement::class, $activeBranch])
            <div class="grid gap-6 lg:grid-cols-2">
                <x-card title="Registrar entrada">
                    <form method="POST" action="{{ route('warehouses.entries.store', $warehouse) }}" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="entry_operation_uuid" value="{{ old('entry_operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-select label="Producto" name="entry_product_id" :options="$productOptions" required />
                            <x-field label="Cantidad" name="entry_quantity" inputmode="decimal" required />
                            <x-field label="Costo unitario (Bs)" name="entry_unit_cost" inputmode="decimal" required />
                            <x-field label="Comprobante del proveedor" name="entry_reference" />
                        </div>
                        <x-field label="Observaciones" name="entry_notes" />
                        <div>
                            <x-button>Registrar entrada</x-button>
                        </div>
                    </form>
                </x-card>

                <x-card title="Registrar salida o consumo">
                    <form method="POST" action="{{ route('warehouses.exits.store', $warehouse) }}" class="flex flex-col gap-4">
                        @csrf
                        <input type="hidden" name="exit_operation_uuid" value="{{ old('exit_operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-select label="Tipo" name="exit_movement_type"
                                :options="collect($exitTypes)->mapWithKeys(fn ($type) => [$type->value => $type->etiqueta()])->all()" />
                            <x-select label="Producto" name="exit_product_id" :options="$productOptions" required />
                            <x-field label="Cantidad" name="exit_quantity" inputmode="decimal" required />
                        </div>
                        <x-field label="Motivo" name="exit_notes" required />
                        <p class="text-sm text-gray-600">Las salidas se valoran al costo promedio y no pueden superar las existencias.</p>
                        <div>
                            <x-button>Registrar salida</x-button>
                        </div>
                    </form>
                </x-card>
            </div>
        @endcan

        @can('ajustar', [\App\Models\InventoryMovement::class, $activeBranch])
            <x-card title="Ajustar por conteo físico">
                <form method="POST" action="{{ route('warehouses.adjustments.store', $warehouse) }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="adjustment_operation_uuid" value="{{ old('adjustment_operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select label="Producto" name="adjustment_product_id" :options="$productOptions" required />
                        <x-field label="Cantidad contada" name="adjustment_counted_quantity" inputmode="decimal" required />
                        <x-field label="Motivo" name="adjustment_notes" required />
                    </div>
                    <p class="text-sm text-gray-600">Se registra la diferencia con las existencias del sistema, como entrada o salida al costo promedio.</p>
                    <div>
                        <x-button variant="secondary">Registrar ajuste</x-button>
                    </div>
                </form>
            </x-card>
        @endcan

        <x-card title="Últimos movimientos">
            @if ($movements->isEmpty())
                <p class="text-sm text-gray-600">Todavía no hay movimientos en este almacén.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Fecha</th>
                                <th class="py-2 pr-4 font-medium">Movimiento</th>
                                <th class="py-2 pr-4 font-medium">Producto</th>
                                <th class="py-2 pr-4 text-right font-medium">Cantidad</th>
                                <th class="py-2 pr-4 text-right font-medium">Costo unitario</th>
                                <th class="py-2 pr-4 text-right font-medium">Saldo</th>
                                <th class="py-2 font-medium">Registró</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 align-top">
                            @foreach ($movements as $movement)
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap">{{ $movement->created_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-4">
                                        {{ $movement->movement_type->etiqueta() }}
                                        @if ($movement->reference || $movement->notes)
                                            <span class="block text-gray-600">{{ collect([$movement->reference, $movement->notes])->filter()->implode(' · ') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4">{{ $movement->product->name }}</td>
                                    <td @class(['py-2 pr-4 text-right whitespace-nowrap', 'text-green-700' => $movement->direction === \App\Enums\InventoryMovementDirection::In, 'text-red-700' => $movement->direction === \App\Enums\InventoryMovementDirection::Out])>
                                        {{ $movement->direction === \App\Enums\InventoryMovementDirection::In ? '+' : '−' }}{{ $movement->quantity }}
                                    </td>
                                    <td class="py-2 pr-4 text-right">Bs {{ $movement->unit_cost }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $movement->balance_after }}</td>
                                    <td class="py-2">{{ $movement->performedByUser->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    @endif
</x-layouts.app>
