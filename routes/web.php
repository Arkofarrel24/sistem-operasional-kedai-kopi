<?php

use App\Http\Controllers\Owner\CategoryController;
use App\Http\Controllers\Owner\IngredientController;
use App\Http\Controllers\Owner\ProductController;
use App\Http\Controllers\Owner\RecipeController;
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
        Route::view('/dashboard', 'owner.dashboard')->name('dashboard');

        // Master data. Products and ingredients are deactivated/kept rather than deleted (history refers to them).
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except(['show', 'destroy']);
        Route::resource('products.recipes', RecipeController::class)->only(['store', 'update', 'destroy'])->scoped();
        Route::resource('ingredients', IngredientController::class)->except(['show', 'destroy']);

        // Placeholders until the related phases are built.
        Route::view('/stock', 'owner.placeholder', ['title' => 'Stok'])->name('stock.index');
        Route::view('/reports', 'owner.placeholder', ['title' => 'Laporan'])->name('reports.index');
        Route::view('/users', 'owner.placeholder', ['title' => 'Pengguna'])->name('users.index');
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
