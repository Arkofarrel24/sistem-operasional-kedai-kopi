<?php

use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Exceptions\StockException;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->stock = app(StockService::class);
    $this->coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000, ['cost_per_unit' => 250]);
});

// Restock

test('restock adds stock and records a restock movement with unit cost and supplier note', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.restock.store', $this->coffee), [
            'quantity' => '500',
            'unit_cost' => '280',
            'note' => 'Toko Sumber Rejeki, nota 0412',
        ])
        ->assertRedirect(route('owner.stock.index'))
        ->assertSessionHas('status', 'Restock Biji Kopi +500 gram dicatat. Harga beli rata-rata sekarang Rp260 / gram.');

    $movement = $this->coffee->stockMovements()->where('type', StockMovementType::Restock)->sole();
    expect($this->coffee->refresh()->current_stock)->toBe('1500.00')
        ->and($movement->quantity)->toBe('500.00')
        ->and($movement->unit_cost)->toBe('280.00')
        ->and($movement->note)->toBe('Toko Sumber Rejeki, nota 0412')
        ->and($movement->user_id)->toBe($this->owner->id);
});

test('restock updates cost per unit with the weighted average', function (string|int $stock, string $cost, string $incoming, string $incomingCost, string $expected) {
    $ingredient = stockedIngredient('Bahan', Unit::Ml, $stock, ['cost_per_unit' => $cost]);

    $this->stock->restock($ingredient, $incoming, $incomingCost, $this->owner);

    expect($ingredient->refresh()->cost_per_unit)->toBe($expected);
})->with([
    '1000 @250 + 500 @280' => [1000, '250', '500', '280', '260.00'],
    'rounds half up: 1000 @18 + 333 @19 = 18.2498 -> 18.25' => [1000, '18', '333', '19', '18.25'],
    'rounds down: 1000 @18 + 100 @19 = 18.0909 -> 18.09' => [1000, '18', '100', '19', '18.09'],
    'no stock on hand takes the new price' => [0, '250', '100', '300', '300.00'],
    'cheaper restock lowers the average' => [200, '300', '800', '250', '260.00'],
]);

test('restock validation messages are in Indonesian', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.restock.store', $this->coffee), ['quantity' => '0', 'unit_cost' => ''])
        ->assertSessionHasErrors([
            'quantity' => 'Jumlah harus lebih besar dari 0.',
            'unit_cost' => 'Harga beli per satuan wajib diisi.',
        ]);

    expect($this->coffee->refresh()->current_stock)->toBe('1000.00');
});

// Stock opname / adjustment

test('adjustment records the difference to the physical count in both directions', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.adjustment.store', $this->coffee), ['counted_stock' => '880', 'reason' => 'Stock opname mingguan'])
        ->assertRedirect(route('owner.stock.index'))
        ->assertSessionHas('status', 'Penyesuaian Biji Kopi dicatat: selisih -120 gram.');

    expect($this->coffee->refresh()->current_stock)->toBe('880.00');

    $this->actingAs($this->owner)
        ->post(route('owner.stock.adjustment.store', $this->coffee), ['counted_stock' => '930.5', 'reason' => 'Ternyata ada 1 bungkus di gudang'])
        ->assertSessionHas('status', 'Penyesuaian Biji Kopi dicatat: selisih +50,5 gram.');

    $adjustments = $this->coffee->stockMovements()->where('type', StockMovementType::Adjustment)->where('note', '!=', 'Stok awal')->orderBy('id')->get();
    expect($this->coffee->refresh()->current_stock)->toBe('930.50')
        ->and($adjustments->pluck('quantity')->all())->toBe(['-120.00', '50.50'])
        ->and($adjustments->pluck('note')->all())->toBe(['Stock opname mingguan', 'Ternyata ada 1 bungkus di gudang'])
        ->and($adjustments->first()->unit_cost)->toBe('250.00');
});

test('adjustment matching the system stock records nothing', function () {
    $before = StockMovement::count();

    $this->actingAs($this->owner)
        ->post(route('owner.stock.adjustment.store', $this->coffee), ['counted_stock' => '1000', 'reason' => 'Cek rutin'])
        ->assertSessionHas('status', 'Stok Biji Kopi sudah sesuai hasil hitung. Tidak ada yang dicatat.');

    expect(StockMovement::count())->toBe($before);
});

test('adjustment requires a reason and a non-negative count', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.adjustment.store', $this->coffee), ['counted_stock' => '-1', 'reason' => ''])
        ->assertSessionHasErrors([
            'counted_stock' => 'Stok hasil hitung minimal 0.',
            'reason' => 'Alasan wajib diisi.',
        ]);

    expect(fn () => $this->stock->adjustToCount($this->coffee, '900', $this->owner, '   '))
        ->toThrow(StockException::class, 'Alasan wajib diisi.');
    expect($this->coffee->refresh()->current_stock)->toBe('1000.00');
});

// Waste

test('waste records a negative movement with its reason', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.waste.store', $this->coffee), ['quantity' => '36', 'reason' => 'Tumpah saat menggiling'])
        ->assertRedirect(route('owner.stock.index'))
        ->assertSessionHas('status', 'Waste Biji Kopi 36 gram dicatat.');

    $movement = $this->coffee->stockMovements()->where('type', StockMovementType::Waste)->sole();
    expect($this->coffee->refresh()->current_stock)->toBe('964.00')
        ->and($movement->quantity)->toBe('-36.00')
        ->and($movement->note)->toBe('Tumpah saat menggiling')
        ->and($movement->unit_cost)->toBe('250.00');
});

test('waste above the current stock is rejected and nothing changes', function () {
    $before = StockMovement::count();

    $this->actingAs($this->owner)
        ->from(route('owner.stock.waste.create', $this->coffee))
        ->post(route('owner.stock.waste.store', $this->coffee), ['quantity' => '1000.01', 'reason' => 'Basi'])
        ->assertRedirect(route('owner.stock.waste.create', $this->coffee))
        ->assertSessionHasErrors(['quantity' => 'Jumlah waste (1.000,01 gram) melebihi stok saat ini (1.000 gram).']);

    expect($this->coffee->refresh()->current_stock)->toBe('1000.00')
        ->and(StockMovement::count())->toBe($before);

    // Wasting exactly everything is allowed.
    $this->stock->recordWaste($this->coffee, '1000', $this->owner, 'Basi semua');
    expect($this->coffee->refresh()->current_stock)->toBe('0.00');
});

test('waste requires a reason', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.stock.waste.store', $this->coffee), ['quantity' => '10', 'reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Alasan wajib diisi.']);
});

test('adjustment, waste and restock never change cost per unit except restock', function () {
    $this->stock->adjustToCount($this->coffee, '900', $this->owner, 'Opname');
    $this->stock->recordWaste($this->coffee, '100', $this->owner, 'Basi');

    expect($this->coffee->refresh()->cost_per_unit)->toBe('250.00');
});
