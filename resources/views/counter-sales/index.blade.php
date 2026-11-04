<x-layouts.app title="Ventas de mostrador">
    @can('venderEnMostrador', [\App\Models\Folio::class, $activeBranch])
        <x-card title="Nueva venta">
            @if ($openCashSessions->isEmpty())
                <p class="text-sm text-gray-600">No hay ninguna caja abierta en la sucursal.</p>
            @elseif ($warehouses->isEmpty())
                <p class="text-sm text-gray-600">La sucursal no tiene almacenes activos.</p>
            @else
                <form method="GET" action="{{ route('counter-sales.index') }}" class="flex flex-wrap items-end gap-3">
                    <x-select label="Almacén" name="almacen" :options="$warehouses->pluck('name', 'id')->all()" :selected="$selectedWarehouse->id" data-auto-submit />
                    <noscript><x-button variant="secondary">Ver productos</x-button></noscript>
                </form>

                @if ($saleBalances->isEmpty())
                    <p class="text-sm text-gray-600">{{ $selectedWarehouse->name }} no tiene productos a la venta con existencias.</p>
                @else
                    <form method="POST" action="{{ route('counter-sales.store') }}" class="flex flex-col gap-4" data-counter-sale>
                        @csrf
                        <input type="hidden" name="operation_uuid" value="{{ old('operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouse->id }}">

                        <label class="flex max-w-sm flex-col gap-1 text-sm font-medium">
                            Buscar producto
                            <input type="search" class="rounded-lg border border-gray-300 px-3 py-2 font-normal" placeholder="Nombre o código" data-counter-sale-search>
                        </label>

                        @error('items')
                            <p class="text-sm text-red-700">{{ $message }}</p>
                        @enderror

                        <div class="max-h-[28rem] overflow-auto rounded-lg border border-gray-200">
                            <table class="w-full text-left text-sm">
                                <thead class="sticky top-0 border-b border-gray-300 bg-gray-50 text-ink-600">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold">Producto</th>
                                        <th class="px-3 py-2 text-right font-semibold">Precio</th>
                                        <th class="px-3 py-2 text-right font-semibold">Existencias</th>
                                        <th class="w-32 px-3 py-2 font-semibold">Cantidad</th>
                                        <th class="px-3 py-2 text-right font-semibold">Importe</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($saleBalances as $saleBalance)
                                        @php($product = $saleBalance->product)
                                        @php($availableQuantity = rtrim(rtrim($saleBalance->quantity, '0'), '.'))
                                        <tr data-counter-sale-row data-search-text="{{ \Illuminate\Support\Str::lower("{$product->name} {$product->sku}") }}" data-price-cents="{{ (int) round((float) $product->sale_price * 100) }}">
                                            <td class="px-3 py-2">
                                                <span class="font-medium">{{ $product->name }}</span>
                                                <span class="block text-xs text-gray-500">{{ $product->sku }} · {{ $product->unit }}</span>
                                                <input type="hidden" name="items[{{ $product->id }}][product_id]" value="{{ $product->id }}">
                                            </td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">Bs {{ $product->sale_price }}</td>
                                            <td class="px-3 py-2 text-right">{{ $availableQuantity }}</td>
                                            <td class="px-3 py-2">
                                                <label class="sr-only" for="quantity-{{ $product->id }}">Cantidad de {{ $product->name }}</label>
                                                <input id="quantity-{{ $product->id }}" name="items[{{ $product->id }}][quantity]" value="{{ old("items.{$product->id}.quantity") }}"
                                                    type="number" min="0" max="{{ $availableQuantity }}" step="0.01" inputmode="decimal"
                                                    @class(['w-24 rounded-lg border px-2 py-1 text-right', 'border-red-500' => $errors->has("items.{$product->id}.quantity"), 'border-gray-300' => ! $errors->has("items.{$product->id}.quantity")])
                                                    data-counter-sale-quantity>
                                                @error("items.{$product->id}.quantity")
                                                    <span class="block text-xs text-red-700">{{ $message }}</span>
                                                @enderror
                                            </td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap" data-counter-sale-line-amount></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <p class="hidden px-3 py-4 text-sm text-gray-600" data-counter-sale-no-results>Ningún producto coincide con la búsqueda.</p>
                        </div>

                        <p class="text-right text-base font-semibold">Total: Bs <span data-counter-sale-total>0.00</span></p>

                        <div class="grid gap-4 sm:grid-cols-4">
                            <x-field label="Cliente (opcional)" name="holder_name" />
                            <x-select label="Caja" name="cash_session_id"
                                :options="$openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all()" />
                            <x-select label="Método" name="payment_method"
                                :options="collect($paymentMethods)->mapWithKeys(fn ($paymentMethod) => [$paymentMethod->value => $paymentMethod->etiqueta()])->all()" />
                            <x-field label="Referencia (tarjeta, transferencia o QR)" name="reference" />
                        </div>
                        <p class="text-sm text-gray-600">Escriba la cantidad de los productos que se llevan. Se cobra el total al precio de venta vigente y los productos salen de {{ $selectedWarehouse->name }}.</p>
                        <div>
                            <x-button>Vender y cobrar</x-button>
                        </div>
                    </form>
                @endif
            @endif
        </x-card>
    @endcan

    <x-card title="Ventas recientes">
        @if ($counterSales->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay ventas de mostrador.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Número</th>
                            <th class="py-2 pr-4 font-semibold">Fecha</th>
                            <th class="py-2 pr-4 font-semibold">Cliente</th>
                            <th class="py-2 pr-4 font-semibold">Método</th>
                            <th class="py-2 pr-4 text-right font-semibold">Total</th>
                            <th class="py-2 pr-4 font-semibold">Estado</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
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
