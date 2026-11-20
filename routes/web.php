<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'active'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'active', 'role:admin|warehouse_staff'])->group(function () {
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'active', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
});

require __DIR__.'/settings.php';
