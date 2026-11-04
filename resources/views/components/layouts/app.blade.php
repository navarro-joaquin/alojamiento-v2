@props(['title'])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased lg:flex">
    {{-- On small screens the checkbox shows and hides the sidebar without JavaScript. --}}
    <input type="checkbox" id="sidebar-toggle" class="peer sr-only">

    <div class="flex items-center justify-between bg-ink-800 px-4 py-3 text-white lg:hidden print:hidden">
        <a href="{{ route('occupancy.index') }}" class="font-serif text-xl font-semibold">{{ config('app.name') }}</a>
        <label for="sidebar-toggle" class="cursor-pointer rounded-lg border border-ink-500 px-3 py-1.5 text-sm font-semibold text-ink-50 hover:bg-ink-700">Menú</label>
    </div>

    <aside class="hidden w-full shrink-0 flex-col gap-6 bg-ink-800 px-4 py-5 text-ink-50 [scrollbar-color:var(--color-ink-600)_transparent] peer-checked:flex lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-68 lg:overflow-y-auto print:hidden">
        <a href="{{ route('occupancy.index') }}" class="hidden px-3 font-serif text-2xl font-semibold tracking-tight text-white lg:block">{{ config('app.name') }}</a>

        @isset($activeBranch)
            <a href="{{ route('active-branch.edit') }}" class="flex flex-col gap-0.5 rounded-lg bg-ink-900 px-3 py-2.5 text-sm ring-1 ring-ink-700 hover:bg-ink-950">
                <span class="text-base font-semibold text-white">{{ $activeBranch->name }}</span>
                <span class="text-ink-200">Fecha de negocio {{ $activeBranch->current_business_date->format('d/m/Y') }}</span>
            </a>
        @endisset

        <nav class="flex flex-col gap-6" aria-label="Menú principal">
            <div class="flex flex-col gap-1">
                <x-nav-link :href="route('occupancy.index')" :active="request()->routeIs('occupancy.*', 'stays.*')">Ocupación</x-nav-link>
                @isset($activeBranch)
                    @can('verCajas', [\App\Models\CashRegister::class, $activeBranch])
                        <x-nav-link :href="route('cash-registers.index')" :active="request()->routeIs('cash-registers.index', 'cash-sessions.*')">Cajas</x-nav-link>
                    @endcan
                    @can('verVentasDeMostrador', [\App\Models\Folio::class, $activeBranch])
                        <x-nav-link :href="route('counter-sales.index')" :active="request()->routeIs('counter-sales.*')">Ventas de mostrador</x-nav-link>
                    @endcan
                    @can('verPendientes', [\App\Models\CheckoutException::class, $activeBranch])
                        <x-nav-link :href="route('checkout-exceptions.index')" :active="request()->routeIs('checkout-exceptions.*')">Salidas con deuda</x-nav-link>
                    @endcan
                    @can('verGastos', [\App\Models\Expense::class, $activeBranch])
                        <x-nav-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')">Gastos</x-nav-link>
                    @endcan
                    @can('verInventario', [\App\Models\InventoryMovement::class, $activeBranch])
                        <x-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')">Inventario</x-nav-link>
                    @endcan
                    @can('verTransferencias', [\App\Models\InventoryTransfer::class, $activeBranch])
                        <x-nav-link :href="route('inventory-transfers.index')" :active="request()->routeIs('inventory-transfers.*')">Transferencias</x-nav-link>
                    @endcan
                    @can('verActivos', [\App\Models\FixedAsset::class, $activeBranch])
                        <x-nav-link :href="route('fixed-assets.index')" :active="request()->routeIs('fixed-assets.*')">Activos fijos</x-nav-link>
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
                        <h2 class="px-3 pb-1 text-xs font-semibold tracking-wider text-ink-200 uppercase">Configuración</h2>
                        <x-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">Habitaciones</x-nav-link>
                        @can('gestionar', [\App\Models\RoomRate::class, $activeBranch])
                            <x-nav-link :href="route('room-rates.index')" :active="request()->routeIs('room-rates.*')">Tarifas</x-nav-link>
                        @endcan
                        @can('gestionar', [\App\Models\CashRegister::class, $activeBranch])
                            <x-nav-link :href="route('cash-registers.create')" :active="request()->routeIs('cash-registers.create', 'cash-registers.edit')">Nueva caja</x-nav-link>
                        @endcan
                        @can('gestionar', [\App\Models\Warehouse::class, $activeBranch])
                            <x-nav-link :href="route('warehouses.index')" :active="request()->routeIs('warehouses.*')">Almacenes</x-nav-link>
                        @endcan
                        @can('gestionarPersonal', [\App\Models\User::class, $activeBranch])
                            <x-nav-link :href="route('staff-members.index')" :active="request()->routeIs('staff-members.*')">Personal</x-nav-link>
                        @endcan
                    </div>
                @endcan
            @endisset

            @can('gestionar', \App\Models\Branch::class)
                <div class="flex flex-col gap-1">
                    <h2 class="px-3 pb-1 text-xs font-semibold tracking-wider text-ink-200 uppercase">Administración</h2>
                    <x-nav-link :href="route('branches.index')" :active="request()->routeIs('branches.*')">Sucursales</x-nav-link>
                    <x-nav-link :href="route('room-types.index')" :active="request()->routeIs('room-types.*')">Tipos de habitación</x-nav-link>
                    <x-nav-link :href="route('expense-categories.index')" :active="request()->routeIs('expense-categories.*')">Categorías de gasto</x-nav-link>
                    <x-nav-link :href="route('products.index')" :active="request()->routeIs('products.*')">Productos</x-nav-link>
                    <x-nav-link :href="route('product-categories.index')" :active="request()->routeIs('product-categories.*')">Categorías de producto</x-nav-link>
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">Usuarios</x-nav-link>
                </div>
            @endcan
        </nav>

        <div class="flex items-center justify-between gap-3 border-t border-ink-700 px-3 pt-4 text-sm lg:mt-auto">
            <span class="truncate text-ink-100">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-semibold text-ink-200 underline-offset-4 hover:text-white hover:underline">Salir</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 lg:px-10 lg:py-8">
            <header class="flex flex-col gap-3 pb-2">
                <h1 class="font-serif text-3xl font-semibold tracking-tight text-ink-900 lg:text-[2.625rem]">{{ $title }}</h1>
                <div class="guilloche-rule print:hidden" aria-hidden="true"></div>
            </header>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-base text-emerald-900 print:hidden" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-base text-red-900" role="alert">
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
