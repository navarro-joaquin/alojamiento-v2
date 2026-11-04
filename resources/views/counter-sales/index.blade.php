@php($productOptions = ['' => 'Sin producto'] + $products->mapWithKeys(fn ($product) => [$product->id => "{$product->name} · Bs {$product->sale_price}"])->all())

<x-layouts.app title="Ventas de mostrador">
    @can('venderEnMostrador', [\App\Models\Folio::class, $activeBranch])
        <x-card title="Nueva venta">
            @if ($openCashSessions->isEmpty())
                <p class="text-sm text-gray-600">No hay ninguna caja abierta en la sucursal.</p>
            @elseif ($warehouses->isEmpty() || $products->isEmpty())
                <p class="text-sm text-gray-600">La sucursal necesita un almacén activo y productos a la venta.</p>
            @else
                <form method="POST" action="{{ route('counter-sales.store') }}" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="operation_uuid" value="{{ old('operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select label="Almacén" name="warehouse_id" :options="$warehouses->pluck('name', 'id')->all()" />
                        <x-field label="Cliente (opcional)" name="holder_name" />
                    </div>
                    <div class="grid gap-3">
                        @for ($row = 0; $row < 5; $row++)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-select label="Producto {{ $row + 1 }}" name="items[{{ $row }}][product_id]" :options="$productOptions" />
                                <x-field label="Cantidad" name="items[{{ $row }}][quantity]" inputmode="decimal" />
                            </div>
                        @endfor
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select label="Caja" name="cash_session_id"
                            :options="$openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all()" />
                        <x-select label="Método" name="payment_method"
                            :options="collect($paymentMethods)->mapWithKeys(fn ($paymentMethod) => [$paymentMethod->value => $paymentMethod->etiqueta()])->all()" />
                        <x-field label="Referencia (tarjeta, transferencia o QR)" name="reference" />
                    </div>
                    <p class="text-sm text-gray-600">Se cobra el total al precio de venta vigente y los productos salen del almacén elegido. Las filas sin producto se ignoran.</p>
                    <div>
                        <x-button>Vender y cobrar</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    @endcan

    <x-card title="Ventas recientes">
        @if ($counterSales->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay ventas de mostrador.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Número</th>
                            <th class="py-2 pr-4 font-medium">Fecha</th>
                            <th class="py-2 pr-4 font-medium">Cliente</th>
                            <th class="py-2 pr-4 font-medium">Método</th>
                            <th class="py-2 pr-4 text-right font-medium">Total</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($counterSales as $counterSale)
                            <tr>
                                <td class="py-2 pr-4 font-medium">#{{ $counterSale->id }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $counterSale->created_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4">{{ $counterSale->holder_name ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $counterSale->payments->first()?->payment_method->etiqueta() }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ number_format((float) ($counterSale->total_amount ?? 0), 2, '.', '') }}</td>
                                <td class="py-2 pr-4">
                                    @if ($counterSale->estaAnulada())
                                        <x-badge color="red">Anulada</x-badge>
                                    @else
                                        <x-badge color="green">Cobrada</x-badge>
                                    @endif
                                </td>
                                <td class="py-2 text-right"><a href="{{ route('counter-sales.show', $counterSale) }}" class="underline">Ver</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
