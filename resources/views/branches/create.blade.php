<x-layouts.app title="Nueva sucursal">
    <form method="POST" action="{{ route('branches.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('branches.partials.form')
        <p class="text-sm text-gray-600">La fecha de negocio empieza hoy, en la zona horaria de la sucursal, y solo la avanza la auditoría nocturna.</p>
        <div class="flex gap-3">
            <x-button>Crear sucursal</x-button>
            <a href="{{ route('branches.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
