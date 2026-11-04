@php($cashSessionOptions = $openCashSessions->mapWithKeys(fn ($cashSession) => [$cashSession->id => $cashSession->cashRegister->name])->all())

<x-layouts.app title="Gastos">
    @can('registrar', [\App\Models\Expense::class, $activeBranch])
        <x-card title="Registrar gasto">
            <form method="POST" action="{{ route('expenses.store') }}" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="operation_uuid" value="{{ old('operation_uuid', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-select label="Categoría" name="expense_category_id"
                        :options="['' => 'Elija una categoría'] + $expenseCategories->pluck('name', 'id')->all()" required />
                    <x-field label="Descripción" name="description" required />
                    <x-field label="Importe (Bs)" name="amount" inputmode="decimal" required />
                    <x-select label="Método de pago" name="payment_method"
                        :options="collect($paymentMethods)->mapWithKeys(fn ($method) => [$method->value => $method->etiqueta()])->all()" />
                    <x-select label="Caja (solo si se paga en efectivo)" name="cash_session_id"
                        :options="['' => 'Sin efectivo'] + $cashSessionOptions" />
                    <x-field label="Comprobante del proveedor" name="reference" />
                </div>
                <p class="text-sm text-gray-600">
                    Hasta Bs {{ $approvalThreshold }} el gasto se confirma al registrarlo. Por encima queda pendiente de que lo apruebe otra persona,
                    y si es en efectivo sale de la caja recién al aprobarse.
                </p>
                <div>
                    <x-button>Registrar gasto</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Pendientes de aprobación">
        @if ($pendingExpenses->isEmpty())
            <p class="text-sm text-gray-600">No hay gastos pendientes de aprobación.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Fecha</th>
                            <th class="py-2 pr-4 font-semibold">Gasto</th>
                            <th class="py-2 pr-4 font-semibold">Método</th>
                            <th class="py-2 pr-4 font-semibold">Registró</th>
                            <th class="py-2 pr-4 text-right font-semibold">Importe</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        @foreach ($pendingExpenses as $expense)
                            <tr>
                                <td class="py-2 pr-4">{{ $expense->expense_date->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">
                                    {{ $expense->description }}
                                    <span class="block text-gray-600">{{ $expense->expenseCategory->name }}{{ $expense->reference ? " · {$expense->reference}" : '' }}</span>
                                </td>
                                <td class="py-2 pr-4">{{ $expense->payment_method->etiqueta() }}</td>
                                <td class="py-2 pr-4">{{ $expense->createdByUser->name }}</td>
                                <td class="py-2 pr-4 text-right">Bs {{ $expense->amount }}</td>
                                <td class="flex flex-col gap-2 py-2">
                                    @can('aprobar', $expense)
                                        <form method="POST" action="{{ route('expenses.approve', $expense) }}" class="flex gap-2">
                                            @csrf
                                            @if ($expense->payment_method->afectaEfectivo())
                                                <label class="sr-only" for="approval-cash-session-{{ $expense->id }}">Caja de la que sale</label>
                                                <select id="approval-cash-session-{{ $expense->id }}" name="approval_cash_session_id" class="rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                                    @foreach ($cashSessionOptions as $cashSessionId => $cashRegisterName)
                                                        <option value="{{ $cashSessionId }}">{{ $cashRegisterName }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <x-button class="py-1">Aprobar</x-button>
                                        </form>
                                    @endcan
                                    @can('rechazar', $expense)
                                        <form method="POST" action="{{ route('expenses.reject', $expense) }}" class="flex gap-2">
                                            @csrf
                                            <label class="sr-only" for="rejection-notes-{{ $expense->id }}">Motivo del rechazo</label>
                                            <input id="rejection-notes-{{ $expense->id }}" name="rejection_notes" required placeholder="Motivo"
                                                class="w-32 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                            <x-button variant="secondary" class="py-1">Rechazar</x-button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <x-card title="Últimos gastos">
        @if ($recentExpenses->isEmpty())
            <p class="text-sm text-gray-600">Todavía no hay gastos registrados.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-300 text-ink-600">
                        <tr>
                            <th class="py-2 pr-4 font-semibold">Fecha</th>
                            <th class="py-2 pr-4 font-semibold">Gasto</th>
                            <th class="py-2 pr-4 font-semibold">Método</th>
                            <th class="py-2 pr-4 font-semibold">Registró</th>
                            <th class="py-2 pr-4 font-semibold">Estado</th>
                            <th class="py-2 pr-4 text-right font-semibold">Importe</th>
                            <th class="py-2 font-semibold"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top">
                        @foreach ($recentExpenses as $expense)
                            <tr>
                                <td class="py-2 pr-4">{{ $expense->expense_date->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">
                                    {{ $expense->description }}
                                    <span class="block text-gray-600">{{ $expense->expenseCategory->name }}{{ $expense->reference ? " · {$expense->reference}" : '' }}</span>
                                </td>
                                <td class="py-2 pr-4">
                                    {{ $expense->payment_method->etiqueta() }}
                                    @if ($expense->cashSession)
                                        <span class="block text-gray-600">{{ $expense->cashSession->cashRegister->name }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    {{ $expense->createdByUser->name }}
                                    @if ($expense->approvedByUser)
                                        <span class="block text-gray-600">Aprobó {{ $expense->approvedByUser->name }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">
                                    <x-badge :color="$expense->status->color()">{{ $expense->status->etiqueta() }}</x-badge>
                                    @if ($expense->resolvedByUser)
                                        <span class="block text-gray-600">{{ $expense->resolvedByUser->name }}: {{ $expense->resolution_notes }}</span>
                                    @endif
                                </td>
                                <td @class(['py-2 pr-4 text-right', 'text-gray-400 line-through' => $expense->status !== \App\Enums\ExpenseStatus::Confirmed])>Bs {{ $expense->amount }}</td>
                                <td class="py-2">
                                    @if ($expense->status === \App\Enums\ExpenseStatus::Confirmed)
                                        @can('anular', $expense)
                                            <form method="POST" action="{{ route('expenses.void', $expense) }}" class="flex gap-2">
                                                @csrf
                                                @if ($expense->payment_method->afectaEfectivo())
                                                    <label class="sr-only" for="void-cash-session-{{ $expense->id }}">Caja a la que vuelve</label>
                                                    <select id="void-cash-session-{{ $expense->id }}" name="void_cash_session_id" class="rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                                        @foreach ($cashSessionOptions as $cashSessionId => $cashRegisterName)
                                                            <option value="{{ $cashSessionId }}" @selected($cashSessionId === $expense->cash_session_id)>{{ $cashRegisterName }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                <label class="sr-only" for="void-reason-{{ $expense->id }}">Motivo de la anulación</label>
                                                <input id="void-reason-{{ $expense->id }}" name="void_reason" required placeholder="Motivo"
                                                    class="w-32 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                                <x-button variant="secondary" class="py-1">Anular</x-button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.app>
