<x-layouts.app title="Editar habitación {{ $room->room_number }}">
    <form method="POST" action="{{ route('rooms.update', $room) }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')
        @include('rooms.partials.form')
        <div class="flex gap-3">
            <x-button>Guardar cambios</x-button>
            <a href="{{ route('rooms.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
