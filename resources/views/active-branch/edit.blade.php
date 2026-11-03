<x-layouts.guest title="Seleccione la sucursal">
    @if ($branches->isEmpty())
        <p class="text-sm text-gray-600">No tiene sucursales asignadas. Pida a un administrador que le asigne un rol.</p>
    @else
        <form method="POST" action="{{ route('active-branch.update') }}" class="flex flex-col gap-3">
            @csrf
            @method('PUT')

            @foreach ($branches as $branch)
                <label class="flex items-center gap-3 rounded-md border border-gray-200 px-3 py-2 text-sm has-checked:border-gray-900">
                    <input type="radio" name="branch_id" value="{{ $branch->id }}" @checked($branch->id === $activeBranchId) required>
                    <span class="flex flex-col">
                        <span class="font-medium">{{ $branch->name }}</span>
                        <span class="text-gray-500">{{ $branch->code }}</span>
                    </span>
                </label>
            @endforeach

            @error('branch_id')
                <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror

            <x-button>Continuar</x-button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="text-sm text-gray-500 hover:text-gray-900">Salir</button>
    </form>
</x-layouts.guest>
