@php($status = $inventoryTransfer->status)
@php($timezone = $activeBranch->timezone)

<x-layouts.app title="Transferencia #{{ $inventoryTransfer->id }}">
    <div>
        <a href="{{ route('inventory-transfers.index') }}" class="text-sm text-gray-600 underline">Volver a transferencias</a>
    </div>

    <x-card>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Estado</dt>
                <dd><x-badge :color="$status->color()">{{ $status->etiqueta() }}</x-badge></dd>
            </div>
            <div>
                <dt class="text-gray-500">Desde</dt>
                <dd class="font-medium">{{ $inventoryTransfer->fromWarehouse->branch->name }} · {{ $inventoryTransfer->fromWarehouse->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Hacia</dt>
                <dd class="font-medium">{{ $inventoryTransfer->toWarehouse->branch->name }} · {{ $inventoryTransfer->toWarehouse->name }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Creada</dt>
                <dd>{{ $inventoryTransfer->created_at->timezone($timezone)->format('d/m/Y H:i') }} por {{ $inventoryTransfer->createdByUser->name }}</dd>
            </div>
            @if ($inventoryTransfer->sent_at)
                <div>
                    <dt class="text-gray-500">Enviada</dt>
                    <dd>{{ $inventoryTransfer->sent_at->timezone($timezone)->format('d/m/Y H:i') }} por {{ $inventoryTransfer->sentByUser->name }}</dd>
                </div>
            @endif
            @if ($inventoryTransfer->received_at)
                <div>
                    <dt class="text-gray-500">Recibida</dt>
                    <dd>{{ $inventoryTransfer->received_at->timezone($timezone)->format('d/m/Y H:i') }} por {{ $inventoryTransfer->receivedByUser->name }}</dd>
                </div>
            @endif
            @if ($inventoryTransfer->cancelled_at)
                <div>
                    <dt class="text-gray-500">Cancelada</dt>
                    <dd>{{ $inventoryTransfer->cancelled_at->timezone($timezone)->format('d/m/Y H:i') }} por {{ $inventoryTransfer->cancelledByUser->name }}</dd>
                </div>
            @endif
            @if ($inventoryTransfer->notes)
                <div class="sm:col-span-3">
                    <dt class="text-gray-500">Observaciones</dt>
                    <dd>{{ $inventoryTransfer->notes }}</dd>
                </div>
            @endif
            @if ($inventoryTransfer->reception_notes)
                <div class="sm:col-span-3">
                    <dt class="text-gray-500">Faltante en la recepción</dt>
                    <dd>{{ $inventoryTransfer->reception_notes }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    @php($canReceive = $status === \App\Enums\InventoryTransferStatus::Sent && $inventoryTransfer->toWarehouse->branch_id === $activeBranch->id && auth()->user()->can('recibir', $inventoryTransfer))

    <x-card title="Productos">
        <form method="POST" action="{{ route('inventory-transfers.receive', $inventoryTransfer) }}" class="flex flex-col gap-4">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Producto</th>
                            <th class="py-2 pr-4 text-right font-semibold">Enviada</th>
                            <th class="py-2 pr-4 text-right font-semibold">Costo unitario</th>
                            <th class="py-2 text-right font-semibold">Recibida</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($inventoryTransfer->items as $item)
                            <tr>
                                <td class="py-2 pr-4">{{ $item->product->name }}</td>
                                <td class="py-2 pr-4 text-right">{{ $item->quantity }} {{ $item->product->unit }}</td>
                                <td class="py-2 pr-4 text-right">{{ $item->unit_cost !== null ? 'Bs '.$item->unit_cost : '—' }}</td>
                                <td class="py-2 text-right">
                                    @if ($canReceive)
                                        <x-field :label="'Recibida de '.$item->product->name" name="received[{{ $item->product_id }}]" :value="$item->quantity" inputmode="decimal" class="w-28 text-right" />
                                    @else
                                        {{ $item->received_quantity ?? '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($canReceive)
                <x-field label="Motivo del faltante (si se recibe menos de lo enviado)" name="reception_notes" />
                <div>
                    <x-button>Recibir transferencia</x-button>
                </div>
            @endif
        </form>
    </x-card>

    @if ($status === \App\Enums\InventoryTransferStatus::Draft && $inventoryTransfer->fromWarehouse->branch_id === $activeBranch->id)
        <div class="flex gap-3">
            @can('enviar', $inventoryTransfer)
                <form method="POST" action="{{ route('inventory-transfers.send', $inventoryTransfer) }}">
                    @csrf
                    <x-button>Enviar transferencia</x-button>
                </form>
            @endcan
            @can('cancelar', $inventoryTransfer)
                <form method="POST" action="{{ route('inventory-transfers.cancel', $inventoryTransfer) }}">
                    @csrf
                    <x-button variant="danger">Cancelar borrador</x-button>
                </form>
            @endcan
        </div>
    @endif
</x-layouts.app>
