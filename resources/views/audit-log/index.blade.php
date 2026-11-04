@php($logLabels = ['cash' => 'Caja', 'reception' => 'Recepción', 'security' => 'Seguridad'])

<x-layouts.app title="Auditoría">
    <form method="GET" action="{{ route('audit-log.index') }}" class="flex flex-wrap items-end gap-3">
        <x-select label="Registro" name="registro" :selected="request('registro')"
            :options="['' => 'Todos'] + $logNames->mapWithKeys(fn ($logName) => [$logName => $logLabels[$logName] ?? $logName])->all()" />
        <x-field label="Evento" name="evento" :value="request('evento')" placeholder="payment, expense.approved…" />
        <x-field label="Desde" name="desde" type="date" :value="request('desde')" />
        <x-field label="Hasta" name="hasta" type="date" :value="request('hasta')" />
        <x-button variant="secondary">Filtrar</x-button>
    </form>

    <x-card>
        @if ($activities->isEmpty())
            <p class="text-sm text-gray-600">No hay registros con esos filtros.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Fecha</th>
                            <th class="py-2 pr-4 font-semibold">Usuario</th>
                            <th class="py-2 pr-4 font-semibold">Evento</th>
                            <th class="py-2 pr-4 font-semibold">Registro</th>
                            <th class="py-2 font-semibold">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        @foreach ($activities as $activity)
                            <tr>
                                <td class="py-2 pr-4 whitespace-nowrap">{{ $activity->created_at->timezone($activeBranch->timezone)->format('d/m/Y H:i:s') }}</td>
                                <td class="py-2 pr-4">{{ $activity->causer?->name ?? 'Sistema' }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">
                                    {{ $activity->event }}
                                    <span class="block text-gray-500">{{ $activity->subject_type }} #{{ $activity->subject_id }}</span>
                                </td>
                                <td class="py-2 pr-4">{{ $logLabels[$activity->log_name] ?? $activity->log_name }}</td>
                                <td class="py-2">
                                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 text-xs">
                                        @foreach ($activity->properties->except(['branch_id']) as $key => $value)
                                            <dt class="text-gray-500">{{ $key }}</dt>
                                            <dd class="break-all font-mono">{{ is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</dd>
                                        @endforeach
                                    </dl>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        @endif
    </x-card>
    <p class="text-sm text-gray-600">Solo lectura. Las operaciones globales (sucursales y cuentas de usuario) no pertenecen a una sucursal y no aparecen aquí.</p>
</x-layouts.app>
