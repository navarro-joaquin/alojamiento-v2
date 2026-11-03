<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Employee;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomStatusLog;
use App\Models\RoomType;
use App\Models\Stay;
use App\Models\StayCharge;
use App\Models\StayRoomAssignment;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registrarMorphMap();
        $this->resolverAccesoGlobal();
        $this->limitarIntentosDeIngreso();
    }

    /**
     * Five login attempts per minute for each email and IP address.
     */
    private function limitarIntentosDeIngreso(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }

    /**
     * Inactive users are denied every ability and super admins are granted
     * every ability before any policy runs.
     */
    private function resolverAccesoGlobal(): void
    {
        Gate::before(fn (User $user): ?bool => match (true) {
            ! $user->estaHabilitado() => false,
            $user->is_super_admin => true,
            default => null,
        });
    }

    /**
     * Store stable aliases instead of class names in polymorphic columns
     * (cash movements, activity log, permissions).
     */
    private function registrarMorphMap(): void
    {
        Relation::enforceMorphMap([
            'branch' => Branch::class,
            'cash_movement' => CashMovement::class,
            'cash_register' => CashRegister::class,
            'cash_session' => CashSession::class,
            'employee' => Employee::class,
            'guest' => Guest::class,
            'payment' => Payment::class,
            'payment_refund' => PaymentRefund::class,
            'permission' => Permission::class,
            'role' => Role::class,
            'room' => Room::class,
            'room_rate' => RoomRate::class,
            'room_status_log' => RoomStatusLog::class,
            'room_type' => RoomType::class,
            'stay' => Stay::class,
            'stay_charge' => StayCharge::class,
            'stay_room_assignment' => StayRoomAssignment::class,
            'user' => User::class,
        ]);
    }
}
