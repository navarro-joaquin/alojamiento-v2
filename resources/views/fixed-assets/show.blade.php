@php($isRetired = $fixedAsset->status === \App\Enums\FixedAssetStatus::Retired)

<x-layouts.app title="{{ $fixedAsset->asset_code }} · {{ $fixedAsset->name }}">
    <div class="flex gap-4 text-sm">
        <a href="{{ route('fixed-assets.index') }}" class="text-gray-600 underline">Volver a activos fijos</a>
        @can('gestionar', [\App\Models\FixedAsset::class, $activeBranch])
            <a href="{{ route('fixed-assets.edit', $fixedAsset) }}" class="text-gray-600 underline">Editar datos</a>
        @endcan
    </div>

    <x-card>
        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500">Estado</dt>
                <dd><x-badge :color="$fixedAsset->status->color()">{{ $fixedAsset->status->etiqueta() }}</x-badge></dd>
            </div>
            <div>
                <dt class="text-gray-500">Ubicación</dt>
                <dd>{{ $fixedAsset->room ? "Habitación {$fixedAsset->room->room_number}" : 'Sin habitación' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Número de serie</dt>
                <dd>{{ $fixedAsset->serial_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Fecha de compra</dt>
                <dd>{{ $fixedAsset->purchase_date?->format('d/m/Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Costo de compra</dt>
                <dd>{{ $fixedAsset->purchase_cost !== null ? "Bs {$fixedAsset->purchase_cost}" : '—' }}</dd>
            </div>
        </dl>
    </x-card>

    @unless ($isRetired)
        <div class="grid gap-6 lg:grid-cols-2">
            @can('gestionar', [\App\Models\FixedAsset::class, $activeBranch])
                <x-card title="Mover o cambiar de estado">
                    <form method="POST" action="{{ route('fixed-assets.movements.store', $fixedAsset) }}" class="flex flex-col gap-3">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-select label="Sucursal" name="to_branch_id" :options="$branchOptions" :selected="$fixedAsset->branch_id" />
                            <x-select label="Estado" name="to_status" :selected="$fixedAsset->status->value"
                                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->etiqueta()])->all()" />
                            <x-select label="Habitación (solo en uso, de esta sucursal)" name="to_room_id" :options="$roomOptions" :selected="$fixedAsset->room_id" />
                            <x-field label="Observaciones" name="movement_notes" />
                        </div>
                        <p class="text-sm text-gray-600">Si lo envía a otra sucursal, pasa a gestionarlo esa sucursal; allí se le asigna habitación.</p>
                        <div>
                            <x-button>Registrar movimiento</x-button>
                        </div>
                    </form>
                </x-card>
            @endcan

            @can('darDeBaja', $fixedAsset)
                <x-card title="Dar de baja">
                    <form method="POST" action="{{ route('fixed-assets.retire', $fixedAsset) }}" class="flex flex-col gap-3">
                        @csrf
                        <x-field label="Motivo de la baja" name="retirement_reason" required />
                        <p class="text-sm text-gray-600">La baja es definitiva: el activo ya no se puede mover.</p>
                        <div>
                            <x-button variant="danger">Dar de baja</x-button>
                        </div>
                    </form>
                </x-card>
            @endcan
        </div>
    @endunless

    <x-card title="Historial">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-ink-600">
                    <tr>
                        <th class="py-2 pr-4 font-semibold">Fecha</th>
                        <th class="py-2 pr-4 font-semibold">Desde</th>
                        <th class="py-2 pr-4 font-semibold">Hacia</th>
                        <th class="py-2 pr-4 font-semibold">Observaciones</th>
                        <th class="py-2 font-semibold">Registró</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 align-top">
                    @foreach ($fixedAsset->movements as $movement)
                        <tr>
                            <td class="py-2 pr-4 whitespace-nowrap">{{ $movement->moved_at->timezone($activeBranch->timezone)->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4">
                                @if ($movement->from_status)
                                    {{ $movement->fromBranch->name }}{{ $movement->fromRoom ? " · Habitación {$movement->fromRoom->room_number}" : '' }}
                                    <span class="block text-gray-600">{{ $movement->from_status->etiqueta() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-4">
                                {{ $movement->toBranch->name }}{{ $movement->toRoom ? " · Habitación {$movement->toRoom->room_number}" : '' }}
                                <span class="block text-gray-600">{{ $movement->to_status->etiqueta() }}</span>
                            </td>
                            <td class="py-2 pr-4">{{ $movement->notes }}</td>
                            <td class="py-2">{{ $movement->performedByUser->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
