<x-layouts.app title="Nuevo usuario">
    <form method="POST" action="{{ route('users.store') }}" class="flex flex-col gap-6">
        @csrf
        @include('users.partials.form')
        <div class="flex gap-3">
            <x-button>Crear usuario</x-button>
            <a href="{{ route('users.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
