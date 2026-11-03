<x-layouts.app title="Nuevo tipo de habitación">
    <form method="POST" action="{{ route('room-types.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('room-types.partials.form')
        <div class="flex gap-3">
            <x-button>Crear tipo</x-button>
            <a href="{{ route('room-types.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
