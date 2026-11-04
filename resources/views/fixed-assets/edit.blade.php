<x-layouts.app title="Editar {{ $fixedAsset->asset_code }}">
    <form method="POST" action="{{ route('fixed-assets.update', $fixedAsset) }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')
        @include('fixed-assets.partials.form')
        <p class="text-sm text-gray-600">La ubicación y el estado cambian desde la ficha del activo, con un movimiento.</p>
        <div class="flex gap-3">
            <x-button>Guardar cambios</x-button>
            <a href="{{ route('fixed-assets.show', $fixedAsset) }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
