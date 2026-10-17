<?php

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

        // Placeholders until the related phases are built.
        Route::view('/products', 'owner.placeholder', ['title' => 'Menu'])->name('products.index');
        Route::view('/ingredients', 'owner.placeholder', ['title' => 'Bahan'])->name('ingredients.index');
        Route::view('/stock', 'owner.placeholder', ['title' => 'Stok'])->name('stock.index');
        Route::view('/reports', 'owner.placeholder', ['title' => 'Laporan'])->name('reports.index');
        Route::view('/users', 'owner.placeholder', ['title' => 'Pengguna'])->name('users.index');
    });

Route::middleware(['auth', 'role:cashier'])
    ->prefix('pos')
    ->name('pos.')
    ->group(function () {
        Route::view('/', 'pos.index')->name('index');
    });

require __DIR__.'/auth.php';
