<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashCountController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CashSessionClosingController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CashSessionReviewController;
use App\Http\Controllers\CheckOutController;
use App\Http\Controllers\CheckoutExceptionApprovalController;
use App\Http\Controllers\CheckoutExceptionController;
use App\Http\Controllers\CheckoutExceptionRejectionController;
use App\Http\Controllers\ExpenseApprovalController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseRejectionController;
use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryEntryController;
use App\Http\Controllers\InventoryExitController;
use App\Http\Controllers\OccupancyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentExternalInvoiceController;
use App\Http\Controllers\PaymentRefundController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RoomChangeController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomHousekeepingStatusController;
use App\Http\Controllers\RoomOperationalStatusController;
use App\Http\Controllers\RoomRateController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\StaffMemberController;
use App\Http\Controllers\StayChargeController;
use App\Http\Controllers\StayController;
use App\Http\Controllers\StayDiscountController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoidedExpenseController;
use App\Http\Controllers\VoidedReceiptController;
use App\Http\Controllers\VoidedStayChargeController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/sucursal', [ActiveBranchController::class, 'edit'])->name('active-branch.edit');
    Route::put('/sucursal', [ActiveBranchController::class, 'update'])->name('active-branch.update');

    // Global catalogs (super admin): outside the active branch, so the first branch can be created.
    // The branch already chosen, if any, is still shared so the menu keeps its branch links.
    Route::middleware('share.active.branch')->group(function () {
        Route::resource('sucursales', BranchController::class)->except(['show', 'destroy'])->parameters(['sucursales' => 'branch'])->names('branches');
        Route::resource('tipos-de-habitacion', RoomTypeController::class)->except(['show', 'destroy'])->parameters(['tipos-de-habitacion' => 'roomType'])->names('room-types');
        Route::resource('categorias-de-gasto', ExpenseCategoryController::class)->except(['show', 'destroy'])->parameters(['categorias-de-gasto' => 'expenseCategory'])->names('expense-categories');
        Route::resource('categorias-de-producto', ProductCategoryController::class)->except(['show', 'destroy'])->parameters(['categorias-de-producto' => 'productCategory'])->names('product-categories');
        Route::resource('productos', ProductController::class)->except(['show', 'destroy'])->parameters(['productos' => 'product'])->names('products');
        Route::resource('usuarios', UserController::class)->except(['show', 'destroy'])->parameters(['usuarios' => 'user'])->names('users');
    });

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
        Route::post('/estadias/{stay}/devoluciones', [PaymentRefundController::class, 'store'])->name('stays.refunds.store');
        Route::get('/pagos/{payment}/recibos/nuevo', [ReceiptController::class, 'create'])->name('payments.receipts.create');
        Route::post('/pagos/{payment}/recibos', [ReceiptController::class, 'store'])->name('payments.receipts.store');
        Route::put('/pagos/{payment}/factura-externa', [PaymentExternalInvoiceController::class, 'update'])->name('payments.external-invoice.update');
        Route::get('/recibos/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::post('/recibos/{receipt}/anulacion', [VoidedReceiptController::class, 'store'])->name('receipts.void');
        Route::get('/estadias/{stay}/cambio-de-habitacion', [RoomChangeController::class, 'create'])->name('stays.room-changes.create');
        Route::post('/estadias/{stay}/cambio-de-habitacion', [RoomChangeController::class, 'store'])->name('stays.room-changes.store');
        Route::post('/estadias/{stay}/salida', [CheckOutController::class, 'store'])->name('stays.check-out');
        Route::post('/estadias/{stay}/salida-con-deuda', [CheckoutExceptionController::class, 'store'])->name('stays.checkout-exceptions.store');
        Route::get('/salidas-con-deuda', [CheckoutExceptionController::class, 'index'])->name('checkout-exceptions.index');
        Route::post('/salidas-con-deuda/{checkoutException}/aprobacion', [CheckoutExceptionApprovalController::class, 'store'])->name('checkout-exceptions.approve');
        Route::post('/salidas-con-deuda/{checkoutException}/rechazo', [CheckoutExceptionRejectionController::class, 'store'])->name('checkout-exceptions.reject');

        Route::get('/reportes', [FinancialReportController::class, 'index'])->name('reports.index');
        Route::get('/auditoria', [AuditLogController::class, 'index'])->name('audit-log.index');

        Route::get('/gastos', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/gastos', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::post('/gastos/{expense}/aprobacion', [ExpenseApprovalController::class, 'store'])->name('expenses.approve');
        Route::post('/gastos/{expense}/rechazo', [ExpenseRejectionController::class, 'store'])->name('expenses.reject');
        Route::post('/gastos/{expense}/anulacion', [VoidedExpenseController::class, 'store'])->name('expenses.void');

        Route::get('/inventario', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/almacenes/{warehouse}/entradas', [InventoryEntryController::class, 'store'])->name('warehouses.entries.store');
        Route::post('/almacenes/{warehouse}/salidas', [InventoryExitController::class, 'store'])->name('warehouses.exits.store');
        Route::post('/almacenes/{warehouse}/ajustes', [InventoryAdjustmentController::class, 'store'])->name('warehouses.adjustments.store');

        Route::put('/habitaciones/{room}/estado-operativo', [RoomOperationalStatusController::class, 'update'])->name('rooms.operational-status.update');

        // Branch catalogs (branch admin).
        Route::resource('habitaciones', RoomController::class)->except(['show', 'destroy'])->parameters(['habitaciones' => 'room'])->names('rooms');
        Route::resource('tarifas', RoomRateController::class)->except(['show', 'destroy'])->parameters(['tarifas' => 'roomRate'])->names('room-rates');
        Route::resource('cajas', CashRegisterController::class)->except(['show', 'destroy'])->parameters(['cajas' => 'cashRegister'])->names('cash-registers');
        Route::resource('almacenes', WarehouseController::class)->except(['show', 'destroy'])->parameters(['almacenes' => 'warehouse'])->names('warehouses');
        Route::get('/personal', [StaffMemberController::class, 'index'])->name('staff-members.index');
        Route::post('/personal', [StaffMemberController::class, 'store'])->name('staff-members.store');
        Route::delete('/personal/{user}', [StaffMemberController::class, 'destroy'])->name('staff-members.destroy');
        Route::post('/cajas/{cashRegister}/sesiones', [CashSessionController::class, 'store'])->name('cash-sessions.store');
        Route::get('/sesiones-de-caja/{cashSession}', [CashSessionController::class, 'show'])->name('cash-sessions.show');
        Route::post('/sesiones-de-caja/{cashSession}/cierre', [CashSessionClosingController::class, 'store'])->name('cash-sessions.close');
        Route::post('/sesiones-de-caja/{cashSession}/arqueos', [CashCountController::class, 'store'])->name('cash-sessions.counts.store');
        Route::post('/sesiones-de-caja/{cashSession}/revision', [CashSessionReviewController::class, 'store'])->name('cash-sessions.review');
    });
});
