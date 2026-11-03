<x-layouts.app title="Usuarios">
    <div>
        <a href="{{ route('users.create') }}" class="inline-flex rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700">Nuevo usuario</a>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-gray-500">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Nombre</th>
                        <th class="py-2 pr-4 font-medium">Correo</th>
                        <th class="py-2 pr-4 font-medium">Estado</th>
                        <th class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $user->name }}</td>
                            <td class="py-2 pr-4">{{ $user->email }}</td>
                            <td class="flex gap-2 py-2 pr-4">
                                <x-badge :color="$user->is_active ? 'green' : 'gray'">{{ $user->is_active ? 'Habilitado' : 'Deshabilitado' }}</x-badge>
                                @if ($user->is_super_admin)
                                    <x-badge color="blue">Superadministrador</x-badge>
                                @endif
                            </td>
                            <td class="py-2 text-right"><a href="{{ route('users.edit', $user) }}" class="underline">Editar</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    <p class="text-sm text-gray-600">Los roles de cada usuario los asigna el administrador de cada sucursal, en Configuración → Personal.</p>
</x-layouts.app>
