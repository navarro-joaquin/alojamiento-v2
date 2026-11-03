<x-layouts.app title="Nueva tarifa">
    <form method="POST" action="{{ route('room-rates.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('room-rates.partials.form')
        <div class="flex gap-3">
            <x-button>Crear tarifa</x-button>
            <a href="{{ route('room-rates.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
