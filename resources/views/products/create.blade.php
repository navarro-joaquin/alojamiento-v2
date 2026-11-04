<x-layouts.app title="Nuevo producto">
    <form method="POST" action="{{ route('products.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('products.partials.form')
        <div class="flex gap-3">
            <x-button>Crear producto</x-button>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
