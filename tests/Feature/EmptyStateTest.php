<?php

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->cashier = User::factory()->cashier()->create();
});

test('owner pages render without errors and explain what is missing on an empty database', function (string $url, array $messages) {
    $this->actingAs($this->owner)->get($url)->assertOk()->assertSee($messages);
})->with([
    'dashboard' => ['/owner/dashboard', ['Rp0', 'Minggu lalu belum ada transaksi', 'Belum ada penjualan dalam', 'Semua bahan di atas stok minimum.', 'Belum ada penjualan minggu ini.']],
    'reports' => ['/owner/reports', ['Belum ada transaksi pada periode ini. Coba pilih rentang tanggal lain.', 'Belum ada penjualan pada periode ini, jadi belum ada laba kotor.', 'Tidak ada bahan yang dicatat terbuang pada periode ini.', 'Belum ada transaksi pada periode ini, jadi jam ramai belum bisa dihitung.', 'Belum ada penjualan pada periode ini.']],
    'sales' => ['/owner/sales', ['Belum ada transaksi. Transaksi yang dicatat kasir akan muncul di sini.']],
    'filtered sales' => ['/owner/sales?status=void', ['Tidak ada transaksi yang cocok dengan filter.']],
    'stock' => ['/owner/stock', ['Belum ada bahan.']],
    'ingredients' => ['/owner/ingredients', ['Belum ada bahan.']],
    'products' => ['/owner/products', ['Tidak ada menu yang cocok.']],
    'categories' => ['/owner/categories', ['Belum ada kategori.']],
    'users' => ['/owner/users', ['Pengguna tidak dihapus']],
]);

test('stock history of an ingredient without movements shows an empty state', function () {
    $ingredient = Ingredient::factory()->create(['unit' => Unit::Gram]);

    $this->actingAs($this->owner)->get(route('owner.stock.show', $ingredient))
        ->assertOk()
        ->assertSee('Tidak ada pergerakan stok yang cocok dengan filter.');
});

test('cashier pages render on an empty database', function () {
    $this->actingAs($this->cashier)->get('/pos')->assertOk()
        ->assertSee('Belum ada menu yang bisa dijual. Owner perlu mengaktifkan menu dan melengkapi resepnya.');

    $this->actingAs($this->cashier)->get('/pos/history')->assertOk()->assertSee('Belum ada transaksi hari ini.');
});

test('the excel export works on an empty period', function () {
    $response = $this->actingAs($this->owner)->get('/owner/reports/export')->assertOk();

    $book = IOFactory::load($response->getFile()->getPathname());
    expect($book->getSheetByName('Rekap')->getCell('C8')->getCalculatedValue())->toEqual(0)
        ->and($book->getSheetByName('Laba Kotor')->getCell('B8')->getCalculatedValue())->toEqual(0)
        ->and($book->getSheetByName('Laba Kotor')->getCell('A12')->getValue())->toBe('Tidak ada waste pada periode ini');
});
