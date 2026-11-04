@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased lg:flex">
    {{-- On small screens the checkbox shows and hides the sidebar without JavaScript. --}}
    <input type="checkbox" id="sidebar-toggle" class="peer sr-only">

    <div class="flex items-center justify-between border-b border-gray-200 bg-white px-4 py-3 lg:hidden print:hidden">
        <a href="{{ route('occupancy.index') }}" class="text-base font-semibold">{{ config('app.name') }}</a>
        <label for="sidebar-toggle" class="cursor-pointer rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">Menú</label>
    </div>

    <aside class="hidden w-full shrink-0 flex-col gap-6 border-b border-gray-200 bg-white px-3 py-4 peer-checked:flex lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:overflow-y-auto lg:border-r lg:border-b-0 print:hidden">
        <a href="{{ route('occupancy.index') }}" class="hidden px-3 text-base font-semibold lg:block">{{ config('app.name') }}</a>

        @isset($activeBranch)
            <a href="{{ route('active-branch.edit') }}" class="flex flex-col rounded-md bg-gray-100 px-3 py-2 text-sm hover:bg-gray-200">
                <span class="font-medium text-gray-900">{{ $activeBranch->name }}</span>
                <span class="text-gray-600">Fecha de negocio {{ $activeBranch->current_business_date->format('d/m/Y') }}</span>
            </a>
        @endisset

        <nav class="flex flex-col gap-6" aria-label="Menú principal">
            <div class="flex flex-col gap-1">
                <x-nav-link :href="route('occupancy.index')" :active="request()->routeIs('occupancy.*', 'stays.*')">Ocupación</x-nav-link>
                @isset($activeBranch)
                    @can('verCajas', [\App\Models\CashRegister::class, $activeBranch])
                        <x-nav-link :href="route('cash-registers.index')" :active="request()->routeIs('cash-registers.index', 'cash-sessions.*')">Cajas</x-nav-link>
                    @endcan
                    @can('verPendientes', [\App\Models\CheckoutException::class, $activeBranch])
                        <x-nav-link :href="route('checkout-exceptions.index')" :active="request()->routeIs('checkout-exceptions.*')">Salidas con deuda</x-nav-link>
                    @endcan
                    @can('verGastos', [\App\Models\Expense::class, $activeBranch])
                        <x-nav-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')">Gastos</x-nav-link>
                    @endcan
                    @can('verReportes', $activeBranch)
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Reportes</x-nav-link>
                    @endcan
                    @can('verAuditoria', $activeBranch)
                        <x-nav-link :href="route('audit-log.index')" :active="request()->routeIs('audit-log.*')">Auditoría</x-nav-link>
                    @endcan
                @endisset
            </div>

            @isset($activeBranch)
                @can('gestionar', [\App\Models\Room::class, $activeBranch])
                    <div class="flex flex-col gap-1">
                        <h2 class="px-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">Configuración</h2>
                        <x-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">Habitaciones</x-nav-link>
                        @can('gestionar', [\App\Models\RoomRate::class, $activeBranch])
                            <x-nav-link :href="route('room-rates.index')" :active="request()->routeIs('room-rates.*')">Tarifas</x-nav-link>
                        @endcan
                        @can('gestionar', [\App\Models\CashRegister::class, $activeBranch])
                            <x-nav-link :href="route('cash-registers.create')" :active="request()->routeIs('cash-registers.create', 'cash-registers.edit')">Nueva caja</x-nav-link>
                        @endcan
                        @can('gestionarPersonal', [\App\Models\User::class, $activeBranch])
                            <x-nav-link :href="route('staff-members.index')" :active="request()->routeIs('staff-members.*')">Personal</x-nav-link>
                        @endcan
                    </div>
                @endcan
            @endisset

            @can('gestionar', \App\Models\Branch::class)
                <div class="flex flex-col gap-1">
                    <h2 class="px-3 text-xs font-semibold tracking-wide text-gray-500 uppercase">Administración</h2>
                    <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Sucursales</x-nav-link>
                    <x-nav-link :href="route('room-types.index')" :active="request()->routeIs('room-types.*')">Tipos de habitación</x-nav-link>
                    <x-nav-link :href="route('expense-categories.index')" :active="request()->routeIs('expense-categories.*')">Categorías de gasto</x-nav-link>
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-nav-link>
                </div>
            @endcan
        </nav>

        <div class="flex items-center justify-between gap-3 border-t border-gray-200 px-3 pt-4 text-sm lg:mt-auto">
            <span class="truncate text-gray-600">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-gray-500 hover:text-gray-900">Salir</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 lg:px-8">
            <h1 class="text-xl font-semibold">{{ $title }}</h1>

            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 print:hidden" role="status">{{ session('status') }}</div>
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
        </div>
    </main>
</body>
</html>
