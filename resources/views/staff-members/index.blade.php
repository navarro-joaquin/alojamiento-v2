<x-layouts.app title="Personal de {{ $activeBranch->name }}">
    <x-card title="Asignar rol">
        <form method="POST" action="{{ route('staff-members.store') }}" class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @csrf
            <x-field label="Correo de la cuenta" name="email" type="email" required />
            <x-select label="Rol" name="role" :options="$roles" required />
            <x-button>Asignar</x-button>
        </form>
        <p class="text-sm text-gray-600">Cada persona tiene un solo rol por sucursal: asignar otro reemplaza el actual. Las cuentas las crea el superadministrador.</p>
    </x-card>

    <x-card title="Personal">
        @if ($staffMembers->isEmpty())
            <p class="text-sm text-gray-600">La sucursal todavía no tiene personal.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Nombre</th>
                            <th class="py-2 pr-4 font-semibold">Correo</th>
                            <th class="py-2 pr-4 font-semibold">Rol</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($staffMembers as $staffMember)
                            <tr>
                                <td class="py-2 pr-4 font-medium">
                                    {{ $staffMember->name }}
                                    @unless ($staffMember->is_active)
                                        <x-badge>Deshabilitado</x-badge>
                                    @endunless
                                </td>
                                <td class="py-2 pr-4">{{ $staffMember->email }}</td>
                                <td class="py-2 pr-4">{{ $staffMember->roles->map(fn ($role) => \App\Enums\BranchRole::tryFrom($role->name)?->etiqueta() ?? $role->name)->join(', ') }}</td>
                                <td class="py-2 text-right">
                                    @unless ($staffMember->is(auth()->user()))
                                        <form method="POST" action="{{ route('staff-members.destroy', $staffMember) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-700 underline">Quitar de la sucursal</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
