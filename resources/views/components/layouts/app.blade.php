@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3">
            <nav class="flex items-center gap-6 text-sm font-medium">
                <a href="{{ route('occupancy.index') }}" class="text-base font-semibold">{{ config('app.name') }}</a>
                <a href="{{ route('occupancy.index') }}" @class(['hover:text-gray-900', 'text-gray-900' => request()->routeIs('occupancy.*'), 'text-gray-500' => ! request()->routeIs('occupancy.*')])>Ocupación</a>
                @isset($activeBranch)
                    @can('verCajas', [\App\Models\CashRegister::class, $activeBranch])
                        <a href="{{ route('cash-registers.index') }}" @class(['hover:text-gray-900', 'text-gray-900' => request()->routeIs('cash-*'), 'text-gray-500' => ! request()->routeIs('cash-*')])>Cajas</a>
                    @endcan
                    @can('gestionar', [\App\Models\Room::class, $activeBranch])
                        <details class="relative">
                            <summary class="cursor-pointer list-none text-gray-500 hover:text-gray-900">Configuración</summary>
                            <div class="absolute left-0 z-10 mt-2 flex w-56 flex-col rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                                <a href="{{ route('rooms.index') }}" class="px-3 py-2 hover:bg-gray-50">Habitaciones</a>
                                @can('gestionar', [\App\Models\RoomRate::class, $activeBranch])
                                    <a href="{{ route('room-rates.index') }}" class="px-3 py-2 hover:bg-gray-50">Tarifas</a>
                                @endcan
                                @can('gestionar', [\App\Models\CashRegister::class, $activeBranch])
                                    <a href="{{ route('cash-registers.create') }}" class="px-3 py-2 hover:bg-gray-50">Nueva caja</a>
                                @endcan
                                @can('gestionarPersonal', [\App\Models\User::class, $activeBranch])
                                    <a href="{{ route('staff-members.index') }}" class="px-3 py-2 hover:bg-gray-50">Personal</a>
                                @endcan
                            </div>
                        </details>
                    @endcan
                @endisset
                @can('gestionar', \App\Models\Branch::class)
                    <details class="relative">
                        <summary class="cursor-pointer list-none text-gray-500 hover:text-gray-900">Administración</summary>
                        <div class="absolute left-0 z-10 mt-2 flex w-56 flex-col rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                            <a href="{{ route('branches.index') }}" class="px-3 py-2 hover:bg-gray-50">Sucursales</a>
                            <a href="{{ route('room-types.index') }}" class="px-3 py-2 hover:bg-gray-50">Tipos de habitación</a>
                            <a href="{{ route('users.index') }}" class="px-3 py-2 hover:bg-gray-50">Usuarios</a>
                        </div>
                    </details>
                @endcan
            </nav>

            <div class="flex items-center gap-4 text-sm">
                @isset($activeBranch)
                    <a href="{{ route('active-branch.edit') }}" class="rounded-md bg-gray-100 px-2.5 py-1 text-gray-700 hover:bg-gray-200">
                        {{ $activeBranch->name }} · {{ $activeBranch->current_business_date->format('d/m/Y') }}
                    </a>
                @endisset
                <span class="text-gray-500">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-500 hover:text-gray-900">Salir</button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6">
        <h1 class="text-xl font-semibold">{{ $title }}</h1>

        @if (session('status'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <ul class="flex list-inside list-disc flex-col gap-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
