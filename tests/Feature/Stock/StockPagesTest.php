<?php

use App\Enums\Unit;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Dimas Pratama']);
    $this->cashier = User::factory()->cashier()->create(['name' => 'Ayu Lestari']);
});

test('stock overview shows status and stock value per ingredient', function () {
    stockedIngredient('Biji Kopi', Unit::Gram, 5000, ['min_stock' => 1000, 'cost_per_unit' => 250]);   // ok, Rp1.250.000
    stockedIngredient('Bubuk Matcha', Unit::Gram, 250, ['min_stock' => 300, 'cost_per_unit' => 450]);  // low, Rp112.500
    stockedIngredient('Botol 1 L', Unit::Pcs, 0, ['min_stock' => 10, 'cost_per_unit' => 4500]);        // out, Rp0

    $this->actingAs($this->owner)
        ->get(route('owner.stock.index'))
        ->assertOk()
        ->assertSee('Rp1.362.500') // total stock value
        ->assertSeeInOrder(['Biji Kopi', '5.000 gram', '1.000 gram', 'Aman', 'Rp1.250.000'])
        ->assertSeeInOrder(['Bubuk Matcha', '250 gram', '300 gram', 'Menipis', 'Rp112.500'])
        ->assertSeeInOrder(['Botol 1 L', '0 pcs', '10 pcs', 'Habis', 'Rp0'])
        ->assertSee(['Restock', 'Penyesuaian', 'Waste', 'Riwayat']);
});

test('movement history shows who recorded it and the invoice of sale movements', function () {
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000, ['cost_per_unit' => 250]);
    $americano = productWithRecipe('Americano', 18000, [[$coffee, 18]]);
    $sale = checkout($this->cashier, [[$americano, 2]]);
    app(StockService::class)->recordWaste($coffee, '10', $this->owner, 'Tumpah');

    $this->actingAs($this->owner)
        ->get(route('owner.stock.show', $coffee))
        ->assertOk()
        ->assertSeeInOrder(['Waste', '−10 gram', 'Rp250', 'Dimas Pratama', 'Tumpah'])
        ->assertSeeInOrder(['Penjualan', '−36 gram', 'Rp250', 'Ayu Lestari', $sale->invoice_no])
        ->assertSeeInOrder(['Penyesuaian', '+1.000 gram', 'Stok awal']);
});

test('movement history filters by type and date range', function () {
    $this->travelTo(Carbon::parse('2026-10-10 09:00', 'Asia/Jakarta'));
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $stock = app(StockService::class);
    $stock->recordWaste($coffee, '11', $this->owner, 'Waste tanggal 10');

    $this->travelTo(Carbon::parse('2026-10-15 23:30', 'Asia/Jakarta'));
    $stock->restock($coffee, '500', '260', $this->owner, 'Restock tanggal 15');
    $stock->recordWaste($coffee, '12', $this->owner, 'Waste tanggal 15');

    $this->travelTo(Carbon::parse('2026-10-17 08:00', 'Asia/Jakarta'));
    $stock->recordWaste($coffee, '13', $this->owner, 'Waste tanggal 17');

    $url = fn (array $query) => route('owner.stock.show', [$coffee] + $query);

    $this->actingAs($this->owner)->get($url(['type' => 'waste']))
        ->assertSee(['Waste tanggal 10', 'Waste tanggal 15', 'Waste tanggal 17'])
        ->assertDontSee('Restock tanggal 15')
        ->assertDontSee('Stok awal');

    $this->actingAs($this->owner)->get($url(['from' => '2026-10-15', 'to' => '2026-10-15']))
        ->assertSee(['Restock tanggal 15', 'Waste tanggal 15'])
        ->assertDontSee('Waste tanggal 10')
        ->assertDontSee('Waste tanggal 17');

    $this->actingAs($this->owner)->get($url(['type' => 'waste', 'from' => '2026-10-16']))
        ->assertSee('Waste tanggal 17')
        ->assertDontSee('Waste tanggal 15');

    $this->actingAs($this->owner)->get($url(['from' => '2026-10-17', 'to' => '2026-10-10']))
        ->assertSessionHasErrors('to');
});

test('stock action forms render with the current stock', function () {
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1500, ['cost_per_unit' => 250]);

    foreach (['owner.stock.restock.create', 'owner.stock.adjustment.create', 'owner.stock.waste.create'] as $route) {
        $this->actingAs($this->owner)->get(route($route, $coffee))->assertOk()->assertSee(['Biji Kopi', '1.500 gram', 'Rp250 / gram']);
    }
});

test('stock:verify reports no difference after sales, restock, waste, adjustment and void', function () {
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000, ['cost_per_unit' => 250]);
    $milk = stockedIngredient('Susu UHT', Unit::Ml, 2000, ['cost_per_unit' => 18]);
    $cup = stockedIngredient('Cup', Unit::Pcs, 50, ['cost_per_unit' => 1200]);
    $latte = productWithRecipe('Latte', 25000, [[$coffee, 18], [$milk, 150], [$cup, 1]]);
    $americano = productWithRecipe('Americano', 18000, [[$coffee, 18], [$cup, 1]]);
    $stock = app(StockService::class);

    $first = checkout($this->cashier, [[$latte, 2], [$americano, 3]]);
    checkout($this->cashier, [[$latte, 1]]);
    $stock->restock($coffee, '500', '280', $this->owner, 'Supplier A');
    $stock->recordWaste($milk, '100', $this->owner, 'Basi');
    $stock->adjustToCount($cup, '40', $this->owner, 'Opname');
    app(SaleService::class)->void($first, $this->owner, 'Salah input');

    $this->artisan('stock:verify')
        ->expectsOutputToContain('Semua stok konsisten (3 bahan).')
        ->assertExitCode(0);
});

test('stock:verify reports a cache that drifted from the movements', function () {
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    stockedIngredient('Susu UHT', Unit::Ml, 2000);

    // Simulate a forbidden direct update that bypassed StockService.
    DB::table('ingredients')->where('id', $coffee->id)->update(['current_stock' => 990]);

    $this->artisan('stock:verify')
        ->expectsOutputToContain('Ditemukan selisih pada 1 bahan:')
        ->expectsTable(['Bahan', 'current_stock', 'Total movement', 'Selisih'], [['Biji Kopi', '990.00', '1000.00', '-10.00']])
        ->assertExitCode(1);
});
