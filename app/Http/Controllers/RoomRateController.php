<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRateRequest;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\AuditService;
use App\Services\RateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Changing a rate only affects stays checked in afterwards: every stay keeps
 * the amount agreed at check-in (or at its last room change).
 */
class RoomRateController extends Controller
{
    public function index(Request $request): View
    {
        $branch = $this->sucursalActiva($request);
        Gate::authorize('gestionar', [RoomRate::class, $branch]);

        return view('room-rates.index', [
            'roomRates' => RoomRate::query()
                ->whereBelongsTo($branch)
                ->with('roomType')
                ->orderBy('room_type_id')
                ->orderByDesc('is_active')
                ->orderByDesc('priority')
                ->orderByDesc('valid_from')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('gestionar', [RoomRate::class, $this->sucursalActiva($request)]);

        return view('room-rates.create', [
            'roomRate' => new RoomRate(['priority' => 0, 'is_active' => true, 'valid_from' => $this->sucursalActiva($request)->current_business_date]),
            'roomTypes' => RoomType::query()->orderBy('name')->get(),
        ]);
    }

    public function store(RoomRateRequest $request, RateService $rateService, AuditService $auditService): RedirectResponse
    {
        $roomRate = new RoomRate(['branch_id' => $this->sucursalActiva($request)->id]);
        $this->guardar($roomRate, $request, $rateService, $auditService, 'room_rate.created');

        return redirect()->route('room-rates.index')->with('status', "Tarifa {$roomRate->name} creada.");
    }

    public function edit(Request $request, RoomRate $roomRate): View
    {
        $this->asegurarDeLaSucursalActiva($request, $roomRate->branch_id);
        Gate::authorize('gestionar', [RoomRate::class, $this->sucursalActiva($request)]);

        return view('room-rates.edit', ['roomRate' => $roomRate, 'roomTypes' => RoomType::query()->orderBy('name')->get()]);
    }

    public function update(RoomRateRequest $request, RoomRate $roomRate, RateService $rateService, AuditService $auditService): RedirectResponse
    {
        $this->asegurarDeLaSucursalActiva($request, $roomRate->branch_id);
        $this->guardar($roomRate, $request, $rateService, $auditService, 'room_rate.updated');

        return redirect()->route('room-rates.index')->with('status', "Tarifa {$roomRate->name} actualizada.");
    }

    /**
     * An active rate must not compete with another one on equal terms.
     */
    private function guardar(RoomRate $roomRate, RoomRateRequest $request, RateService $rateService, AuditService $auditService, string $event): void
    {
        $roomRate->fill($request->datosDeTarifa());

        DB::transaction(function () use ($roomRate, $request, $rateService, $auditService, $event): void {
            if ($roomRate->is_active) {
                $rateService->verificarSinSolapamiento($roomRate);
            }

            $roomRate->save();

            $auditService->registrar('reception', $event, $roomRate, $request->user(), $roomRate->branch_id, [
                'amount' => $roomRate->amount,
                'changes' => collect($roomRate->getChanges())->except('updated_at')->all(),
            ]);
        });
    }
}
