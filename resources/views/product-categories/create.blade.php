<x-layouts.app title="Nueva categoría de producto">
    <form method="POST" action="{{ route('product-categories.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('product-categories.partials.form')
        <div class="flex gap-3">
            <x-button>Crear categoría</x-button>
            <a href="{{ route('product-categories.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
