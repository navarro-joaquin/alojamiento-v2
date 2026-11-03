<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\OccupancyController;
use App\Http\Controllers\RoomHousekeepingStatusController;
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
    });
});
