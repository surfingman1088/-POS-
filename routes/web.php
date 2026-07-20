<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->middleware('guest')
    ->name('home');

// dashboard
Volt::route('dashboard', 'main.dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Auth routes
Route::middleware(['auth'])->group(function () {
    // Settings route
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/language', 'settings.language')->name('settings.language');
    Volt::route('settings/qrcode', 'settings.qrcode')->name('settings.qrcode');
    Route::get('settings/discounts', \App\Livewire\Presets\Discount::class)->name('settings.discounts');

    // Orders route
    Volt::route('orders', 'order.dashboard')->name('orders');
    Volt::route('orders/history', 'order.history')->name('orders.history');
    Volt::route('orders/create', 'order.create')->name('orders.create');
    Volt::route('orders/{order}/edit', 'order.edit')->name('orders.edit');
    Volt::route('orders/add', 'order.add')->name('orders.add');

    // Presets route
    Volt::route('presets/discounts', 'presets.discount')->name('presets.discounts');

    // Products route
    Volt::route('products', 'product.dashboard')->name('products');
    Route::get('products/inventory', \App\Livewire\Product\InventoryAudit::class)->name('inventory.audit');
    Volt::route('products/categories', 'product.categories')->name('products.categories');

    // Customers route
    Volt::route('customers', 'customer.dashboard')->name('customers');

    // Employee route — 僅管理員可存取
    Route::middleware(['admin.only'])->group(function () {
        Volt::route('employees', 'employee.dashboard')->name('employees');
        Volt::route('employees/archived', 'employee.archive')->name('employees.archived');
    });

    // Logs and Audits
    Route::get('logs', \App\Livewire\Logs\Log::class)->name('logs');
    Route::get('accounts/sessions', \App\Livewire\Logs\Users::class)->name('accounts.sessions');

    // ─── 倉儲管理路由（admin / warehouse / branch 角色可存取）────────
    Route::middleware(['warehouse.access'])->prefix('warehouse')->name('warehouse.')->group(function () {
        // 倉儲儀表板
        Route::get('/', \App\Livewire\Warehouse\Dashboard::class)->name('dashboard');
        // 入庫管理
        Route::get('/receipt', \App\Livewire\Warehouse\Receipt::class)->name('receipt');
        // 出庫管理
        Route::get('/dispatch', \App\Livewire\Warehouse\Dispatch::class)->name('dispatch');
        // 庫存盤點
        Route::get('/stocktake', \App\Livewire\Warehouse\Stocktake::class)->name('stocktake');
        // 分店庫存查詢
        Route::get('/branch-stock', \App\Livewire\Warehouse\BranchStockView::class)->name('branch-stock');
        // 異動記錄
        Route::get('/movements', \App\Livewire\Warehouse\MovementLog::class)->name('movements');
    });

    Route::get('payment-qr/{path}', function (string $path) {
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    })->where('path', '.*')->name('payment.qr');
});

require __DIR__.'/auth.php';


/**
 * Routes Notes:
 * example route: Volt::route('orders', 'orders')->name('orders');
 *
 * Volt::route -> equivalent to Route::view, Route::get
 *
 * ('first parameter', 'second parameter')->name('third parameter');
 * first parameter is the URL path where the route will be accessible (e.g. .../orders)
 * second parameter is the view file name or controller action that will handle the request (e.g. 'classname.controller', 'orders.index', 'orders.delete')
 * third parameter is an optional name for the route (e.g. 'route.name')
 *
 * Volt::route(
 *   'orders',   // URL path (goes to /orders)
 *   'orders'    // Volt component/view file name (resources/views/livewire/orders.blade.php)
 * )->name(
 *   'orders'    // Route name (used in route('orders'))
 * );
 */
