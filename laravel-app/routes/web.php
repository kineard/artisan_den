<?php

use App\Http\Controllers\KpiController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TimeclockController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('auth.login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

Route::middleware(['auth.session'])->group(function () {
    Route::get('/kpi', [KpiController::class, 'index'])->name('kpi.index');
    Route::post('/kpi', [KpiController::class, 'store'])->middleware('permission:kpi_write')->name('kpi.store');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/products', [InventoryController::class, 'storeProduct'])->middleware('permission:inventory_write')->name('inventory.products.store');
    Route::post('/inventory/items/{item}/update', [InventoryController::class, 'updateInventory'])->middleware('permission:inventory_write')->name('inventory.items.update');
    Route::post('/inventory/items/{item}/order', [InventoryController::class, 'placeOrder'])->middleware('permission:inventory_write')->name('inventory.items.order');

    Route::get('/timeclock', [TimeclockController::class, 'index'])->name('timeclock.index');
    Route::post('/timeclock/employees', [TimeclockController::class, 'storeEmployee'])->middleware('permission:timeclock_manager')->name('timeclock.employees.store');
    Route::post('/timeclock/punch', [TimeclockController::class, 'punch'])->middleware('permission:employee_self_service')->name('timeclock.punch');
});
