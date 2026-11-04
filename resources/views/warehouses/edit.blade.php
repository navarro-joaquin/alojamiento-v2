<x-layouts.app title="Editar {{ $warehouse->name }}">
    <form method="POST" action="{{ route('warehouses.update', $warehouse) }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')
        @include('warehouses.partials.form')
        <div class="flex gap-3">
            <x-button>Guardar cambios</x-button>
            <a href="{{ route('warehouses.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
