<?php

use App\Http\Controllers\Owner\CategoryController;
use App\Http\Controllers\Owner\DashboardController;
use App\Http\Controllers\Owner\IngredientController;
use App\Http\Controllers\Owner\ProductController;
use App\Http\Controllers\Owner\RecipeController;
use App\Http\Controllers\Owner\ReportController;
use App\Http\Controllers\Owner\RestockController;
use App\Http\Controllers\Owner\SaleController as OwnerSaleController;
use App\Http\Controllers\Owner\StockAdjustmentController;
use App\Http\Controllers\Owner\StockController;
use App\Http\Controllers\Owner\UserController;
use App\Http\Controllers\Owner\UserPasswordController;
use App\Http\Controllers\Owner\UserStatusController;
use App\Http\Controllers\Owner\VoidSaleController;
use App\Http\Controllers\Owner\WasteController;
use App\Http\Controllers\Pos\SaleController;
use App\Http\Controllers\Pos\SaleHistoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Guests land on the login page; logged-in users are bounced to their role's home by the guest middleware.
Route::redirect('/', '/login');

// Generic landing route used by Breeze's auth controllers; forwards to the role's home.
Route::get('/dashboard', fn (Request $request) => redirect($request->user()->homeUrl()))
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // Master data. Products and ingredients are deactivated/kept rather than deleted (history refers to them).
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::resource('products.recipes', RecipeController::class)->only(['store', 'update', 'destroy'])->scoped();
        Route::resource('ingredients', IngredientController::class)->except(['show', 'destroy']);

        // Stock: every change goes through StockService (PLAN.md rule 5).
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/{ingredient}', [StockController::class, 'show'])->name('stock.show');
        Route::get('/stock/{ingredient}/restock', [RestockController::class, 'create'])->name('stock.restock.create');
        Route::post('/stock/{ingredient}/restock', [RestockController::class, 'store'])->name('stock.restock.store');
        Route::get('/stock/{ingredient}/adjustment', [StockAdjustmentController::class, 'create'])->name('stock.adjustment.create');
        Route::post('/stock/{ingredient}/adjustment', [StockAdjustmentController::class, 'store'])->name('stock.adjustment.store');
        Route::get('/stock/{ingredient}/waste', [WasteController::class, 'create'])->name('stock.waste.create');
        Route::post('/stock/{ingredient}/waste', [WasteController::class, 'store'])->name('stock.waste.store');

        // Sales and void.
        Route::get('/sales', [OwnerSaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [OwnerSaleController::class, 'show'])->name('sales.show');
        Route::post('/sales/{sale}/void', VoidSaleController::class)->name('sales.void');

        // Reports.
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

        // Users: never deleted, only deactivated (PLAN.md rules 15 and 16).
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('/users/{user}/status', [UserStatusController::class, 'update'])->name('users.status');
        Route::get('/users/{user}/password', [UserPasswordController::class, 'edit'])->name('users.password.edit');
        Route::put('/users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password.update');
    });

Route::middleware(['auth', 'role:cashier'])
    ->prefix('pos')
    ->name('pos.')
    ->group(function () {
        Route::view('/', 'pos.index')->name('index'); // hosts the Livewire register
        Route::get('/history', [SaleHistoryController::class, 'index'])->name('history');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->can('view', 'sale')->name('sales.show');
    });

require __DIR__.'/auth.php';
