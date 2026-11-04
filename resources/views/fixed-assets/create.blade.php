<x-layouts.app title="Nuevo activo fijo">
    <form method="POST" action="{{ route('fixed-assets.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('fixed-assets.partials.form')
        <div class="flex gap-3">
            <x-button>Registrar activo</x-button>
            <a href="{{ route('fixed-assets.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
