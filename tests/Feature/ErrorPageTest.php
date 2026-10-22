<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('unknown pages show the Indonesian 404 page', function () {
    $this->get('/tidak-ada-halaman-ini')
        ->assertNotFound()
        ->assertSee(['Halaman tidak ditemukan', 'Kembali', 'Ke beranda'])
        ->assertDontSee('Not Found');
});

test('forbidden pages show the Indonesian 403 page', function () {
    $this->actingAs(User::factory()->cashier()->create())
        ->get('/owner/dashboard')
        ->assertForbidden()
        ->assertSee(['Akses ditolak', 'Halaman ini tidak tersedia untuk akun Anda.', 'Kembali'])
        ->assertDontSee('Forbidden');
});

test('expired sessions show the Indonesian 419 page', function () {
    Route::middleware('web')->get('/__test/expired', fn () => abort(419));

    $this->get('/__test/expired')
        ->assertStatus(419)
        ->assertSee(['Sesi kedaluwarsa', 'Buka ulang halaman', 'Kembali']);
});

test('server errors show the Indonesian 500 page without leaking details', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/__test/boom', fn () => throw new RuntimeException('database password is hunter2'));

    $this->get('/__test/boom')
        ->assertStatus(500)
        ->assertSee(['Terjadi kesalahan di server', 'Kembali', 'Ke beranda'])
        ->assertDontSee('hunter2');
});

test('too many requests show the Indonesian 429 page', function () {
    Route::middleware('web')->get('/__test/slow-down', fn () => abort(429));

    $this->get('/__test/slow-down')->assertStatus(429)->assertSee('Terlalu banyak permintaan');
});
