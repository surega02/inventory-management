<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    // every role; employees only reach assets they currently borrow (404 otherwise)
    Route::get('assets/{asset}', [AssetController::class, 'show'])->whereNumber('asset')->name('assets.show');
    // every role; LoanService::list limits employees to their own loans
    Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
});

Route::middleware(['auth', 'active', 'role:admin|warehouse_staff'])->group(function () {
    Route::resource('assets', AssetController::class)->except('show');
    Route::post('assets/{asset}/dispose', [AssetController::class, 'dispose'])->name('assets.dispose');
    Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
    Route::post('loans/{loan}/return', [LoanController::class, 'return'])->name('loans.return');
    Route::post('assets/{asset}/movements', [MovementController::class, 'store'])->name('assets.movements.store');
    Route::post('assets/{asset}/maintenances', [MaintenanceController::class, 'store'])->name('assets.maintenances.store');
    Route::post('maintenances/{maintenance}/complete', [MaintenanceController::class, 'complete'])->name('maintenances.complete');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'active', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

require __DIR__.'/settings.php';
