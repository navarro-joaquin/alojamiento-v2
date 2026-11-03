<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\OccupancyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoomChangeController;
use App\Http\Controllers\RoomHousekeepingStatusController;
use App\Http\Controllers\StayChargeController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\StayDiscountController;
use App\Http\Controllers\VoidedStayChargeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/sucursal', [ActiveBranchController::class, 'edit'])->name('active-branch.edit');
    Route::put('/sucursal', [ActiveBranchController::class, 'update'])->name('active-branch.update');

    Route::middleware('active.branch')->group(function () {
        Route::get('/', [OccupancyController::class, 'index'])->name('occupancy.index');
        Route::put('/habitaciones/{room}/limpieza', [RoomHousekeepingStatusController::class, 'update'])->name('rooms.housekeeping-status.update');

        Route::get('/habitaciones/{room}/ingreso', [StayController::class, 'create'])->name('stays.create');
        Route::post('/habitaciones/{room}/ingreso', [StayController::class, 'store'])->name('stays.store');
        Route::get('/estadias/{stay}', [StayController::class, 'show'])->name('stays.show');

        Route::post('/estadias/{stay}/cargos', [StayChargeController::class, 'store'])->name('stays.charges.store');
        Route::post('/estadias/{stay}/descuentos', [StayDiscountController::class, 'store'])->name('stays.discounts.store');
        Route::post('/cargos/{stayCharge}/anulacion', [VoidedStayChargeController::class, 'store'])->name('stay-charges.void');
        Route::post('/estadias/{stay}/pagos', [PaymentController::class, 'store'])->name('stays.payments.store');
        Route::get('/estadias/{stay}/cambio-de-habitacion', [RoomChangeController::class, 'create'])->name('stays.room-changes.create');
        Route::post('/estadias/{stay}/cambio-de-habitacion', [RoomChangeController::class, 'store'])->name('stays.room-changes.store');
        Route::post('/estadias/{stay}/salida', [CheckOutController::class, 'store'])->name('stays.check-out');
    });
});
