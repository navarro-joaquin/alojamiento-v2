<x-layouts.app title="Nuevo almacén">
    <form method="POST" action="{{ route('warehouses.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('warehouses.partials.form')
        <div class="flex gap-3">
            <x-button>Crear almacén</x-button>
            <a href="{{ route('warehouses.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
