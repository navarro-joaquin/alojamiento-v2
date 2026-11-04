<x-layouts.app title="Activos fijos">
    @can('gestionar', [\App\Models\FixedAsset::class, $activeBranch])
        <div>
            <a href="{{ route('fixed-assets.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nuevo activo</a>
        </div>
    @endcan

    <x-card>
        @if ($fixedAssets->isEmpty())
            <p class="text-sm text-gray-600">La sucursal todavía no tiene activos fijos.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Código</th>
                            <th class="py-2 pr-4 font-medium">Nombre</th>
                            <th class="py-2 pr-4 font-medium">Ubicación</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($fixedAssets as $fixedAsset)
                            <tr>
                                <td class="py-2 pr-4 font-mono">{{ $fixedAsset->asset_code }}</td>
                                <td class="py-2 pr-4 font-medium">{{ $fixedAsset->name }}</td>
                                <td class="py-2 pr-4">{{ $fixedAsset->room ? "Habitación {$fixedAsset->room->room_number}" : '—' }}</td>
                                <td class="py-2 pr-4"><x-badge :color="$fixedAsset->status->color()">{{ $fixedAsset->status->etiqueta() }}</x-badge></td>
                                <td class="py-2 text-right"><a href="{{ route('fixed-assets.show', $fixedAsset) }}" class="underline">Ver</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($retiredFixedAssets->isNotEmpty())
        <x-card title="Dados de baja">
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($retiredFixedAssets as $retiredFixedAsset)
                    <li><a href="{{ route('fixed-assets.show', $retiredFixedAsset) }}" class="underline">{{ $retiredFixedAsset->asset_code }} · {{ $retiredFixedAsset->name }}</a></li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
