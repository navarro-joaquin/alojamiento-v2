<x-layouts.app title="Editar {{ $branch->name }}">
    <form method="POST" action="{{ route('branches.update', $branch) }}" class="flex flex-col gap-6">
        @csrf
        @method('PUT')
        @include('branches.partials.form')
        <div class="flex gap-3">
            <x-button>Guardar cambios</x-button>
            <a href="{{ route('branches.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-gray-900">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
