<x-layouts.app title="Reportes">
    <form method="GET" action="{{ route('reports.index') }}" class="flex flex-wrap items-end gap-3">
        <x-field label="Desde (fecha de negocio)" name="desde" type="date" :value="$fromDate->toDateString()" />
        <x-field label="Hasta" name="hasta" type="date" :value="$toDate->toDateString()" />
        <x-button variant="secondary">Ver</x-button>
    </form>
    <p class="text-sm text-gray-600">
        Del {{ $fromDate->format('d/m/Y') }} al {{ $toDate->format('d/m/Y') }}. Los cobros y devoluciones de cada fecha de negocio van de las
        {{ str_pad((string) config('lodging.night_audit_hour'), 2, '0', STR_PAD_LEFT) }}:00 de ese día a la misma hora del día siguiente.
    </p>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card title="Ocupación">
            <p class="text-3xl font-semibold">{{ $report['occupancy']['rate'] }} %</p>
            <p class="text-sm text-gray-600">{{ $report['occupancy']['nightsSold'] }} noches vendidas de {{ $report['occupancy']['roomNights'] }} disponibles.</p>
        </x-card>

        <x-card title="Cargos y descuentos" class="lg:col-span-2">
            @if ($report['charges']->isEmpty())
                <p class="text-sm text-gray-600">Sin cargos en el período.</p>
            @else
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['charges'] as $row)
                            <tr>
                                <td class="py-1 pr-4">{{ $row['charge_type']->etiqueta() }}</td>
                                <td @class(['py-1 text-right', 'text-red-700' => $row['direction'] === \App\Enums\ChargeDirection::Credit])>
                                    {{ $row['direction'] === \App\Enums\ChargeDirection::Credit ? '−' : '' }}Bs {{ $row['total'] }}
                                </td>
                            </tr>
                        @endforeach
                        <tr class="font-semibold">
                            <td class="py-1 pr-4">Total facturable</td>
                            <td class="py-1 text-right">Bs {{ $report['chargesNet'] }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <x-card title="Dinero por método de pago">
        @if ($report['netByMethod']->isEmpty())
            <p class="text-sm text-gray-600">Sin cobros, devoluciones ni gastos en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Método</th>
                            <th class="py-2 pr-4 text-right font-medium">Cobros</th>
                            <th class="py-2 pr-4 text-right font-medium">Devoluciones</th>
                            <th class="py-2 pr-4 text-right font-medium">Gastos</th>
                            <th class="py-2 text-right font-medium">Neto</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['netByMethod'] as $methodValue => $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['method']->etiqueta() }}</td>
                                <td class="py-2 pr-4 text-right">
                                    Bs {{ $row['payments'] }}
                                    <span class="text-gray-500">({{ $report['payments'][$methodValue]['count'] ?? 0 }})</span>
                                </td>
                                <td class="py-2 pr-4 text-right">Bs {{ $row['refunds'] }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $row['expenses'] }}</td>
                                <td class="py-2 text-right font-semibold">Bs {{ $row['net'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card title="Gastos por categoría">
            @if ($report['expensesByCategory']->isEmpty())
                <p class="text-sm text-gray-600">Sin gastos confirmados en el período.</p>
            @else
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['expensesByCategory'] as $row)
                            <tr>
                                <td class="py-1 pr-4">{{ $row['category'] }} <span class="text-gray-500">({{ $row['count'] }})</span></td>
                                <td class="py-1 text-right">Bs {{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card title="Saldos pendientes">
            @if ($outstandingStays->isEmpty())
                <p class="text-sm text-gray-600">Ninguna estadía debe dinero.</p>
            @else
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($outstandingStays as $row)
                            <tr>
                                <td class="py-1 pr-4">
                                    <a href="{{ route('stays.show', $row['stay']) }}" class="underline">Hab. {{ $row['stay']->room->room_number }}</a>
                                    · {{ $row['stay']->guests->first()?->first_name }} {{ $row['stay']->guests->first()?->last_name }}
                                    <x-badge :color="$row['stay']->status === \App\Enums\StayStatus::Active ? 'blue' : 'amber'">{{ $row['stay']->status->etiqueta() }}</x-badge>
                                </td>
                                <td class="py-1 text-right font-semibold">Bs {{ $row['balance'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-gray-500">Estadías activas con deuda y estadías finalizadas que salieron con deuda autorizada. No depende del período elegido.</p>
            @endif
        </x-card>
    </div>

    <x-card title="Cierres de caja">
        @if ($report['cashSessions']->isEmpty())
            <p class="text-sm text-gray-600">Sin sesiones de caja en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Fecha</th>
                            <th class="py-2 pr-4 font-medium">Caja</th>
                            <th class="py-2 pr-4 font-medium">Cerrada por</th>
                            <th class="py-2 pr-4 text-right font-medium">Esperado</th>
                            <th class="py-2 pr-4 text-right font-medium">Contado</th>
                            <th class="py-2 pr-4 text-right font-medium">Diferencia</th>
                            <th class="py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($report['cashSessions'] as $cashSession)
                            <tr>
                                <td class="py-2 pr-4">{{ $cashSession->business_date->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4"><a href="{{ route('cash-sessions.show', $cashSession) }}" class="underline">{{ $cashSession->cashRegister->name }}</a></td>
                                <td class="py-2 pr-4">{{ $cashSession->closedByUser?->name }}</td>
                                <td class="py-2 pr-4 text-right">{{ $cashSession->expected_amount ? "Bs {$cashSession->expected_amount}" : '' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $cashSession->declared_amount ? "Bs {$cashSession->declared_amount}" : '' }}</td>
                                <td @class(['py-2 pr-4 text-right', 'font-semibold text-red-700' => $cashSession->difference_amount !== null && $cashSession->difference_amount !== '0.00'])>
                                    {{ $cashSession->difference_amount !== null ? "Bs {$cashSession->difference_amount}" : '' }}
                                </td>
                                <td class="py-2"><x-badge :color="$cashSession->status->color()">{{ $cashSession->status->etiqueta() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Cobros sin factura externa">
        @if ($report['paymentsWithoutInvoice']->isEmpty())
            <p class="text-sm text-gray-600">Todos los cobros del período tienen su número de factura.</p>
        @else
            <ul class="flex flex-col gap-1 text-sm">
                @foreach ($report['paymentsWithoutInvoice'] as $payment)
                    <li>
                        <a href="{{ route('stays.show', $payment->folio->stay) }}" class="underline">Hab. {{ $payment->folio->stay->room->room_number }}</a>
                        · {{ $payment->paid_at->timezone($activeBranch->timezone)->format('d/m H:i') }}
                        · {{ $payment->payment_method->etiqueta() }} · Bs {{ $payment->amount }}
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-layouts.app>
