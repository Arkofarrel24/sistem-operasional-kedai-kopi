<?php

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Exceptions\SaleException;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Carbon\Carbon;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create(['name' => 'Dimas Pratama']);
    $this->cashier = User::factory()->cashier()->create(['name' => 'Ayu Lestari']);
    $this->coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000, ['cost_per_unit' => 250]);
    $this->milk = stockedIngredient('Susu UHT', Unit::Ml, 2000, ['cost_per_unit' => 18]);
    $this->cup = stockedIngredient('Cup', Unit::Pcs, 50, ['cost_per_unit' => 1200]);
    $this->latte = productWithRecipe('Latte', 25000, [[$this->coffee, 18], [$this->milk, 150], [$this->cup, 1]]);
    $this->americano = productWithRecipe('Americano', 18000, [[$this->coffee, 18], [$this->cup, 1]]);
});

test('void returns exactly what a multi-item sale deducted', function () {
    $other = checkout($this->cashier, [[$this->americano, 1]]); // stays completed
    $before = [$this->coffee->refresh()->current_stock, $this->milk->refresh()->current_stock, $this->cup->refresh()->current_stock];

    $sale = checkout($this->cashier, [[$this->latte, 2], [$this->americano, 3]]);
    expect($this->coffee->refresh()->current_stock)->toBe('892.00'); // 1000 - 18 - 18 x 5

    app(SaleService::class)->void($sale, $this->owner, 'Pelanggan batal');

    expect([$this->coffee->refresh()->current_stock, $this->milk->refresh()->current_stock, $this->cup->refresh()->current_stock])->toBe($before);

    $returns = $sale->stockMovements()->where('type', StockMovementType::VoidReturn)->get()->keyBy('ingredient_id');
    expect($returns)->toHaveCount(3)
        ->and($returns[$this->coffee->id]->quantity)->toBe('90.00')
        ->and($returns[$this->milk->id]->quantity)->toBe('300.00')
        ->and($returns[$this->cup->id]->quantity)->toBe('5.00')
        ->and($returns->pluck('sale_id')->unique()->all())->toBe([$sale->id])
        ->and($returns->pluck('user_id')->unique()->all())->toBe([$this->owner->id])
        ->and($other->refresh()->status)->toBe(SaleStatus::Completed);
});

test('void keeps the sale with status, reason and time instead of deleting it', function () {
    $this->travelTo(Carbon::parse('2026-10-17 14:05', 'Asia/Jakarta'));
    $sale = checkout($this->cashier, [[$this->latte, 1]]);

    app(SaleService::class)->void($sale, $this->owner, 'Salah input menu');

    $sale = Sale::find($sale->id);
    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe(SaleStatus::Void)
        ->and($sale->void_reason)->toBe('Salah input menu')
        ->and($sale->voided_at->equalTo(now()))->toBeTrue()
        ->and($sale->items)->toHaveCount(1)
        ->and($sale->voidedBy()->is($this->owner))->toBeTrue();
});

test('void returns what was deducted even after the recipe changed', function () {
    $sale = checkout($this->cashier, [[$this->latte, 2]]);

    $this->latte->recipes()->where('ingredient_id', $this->milk->id)->update(['quantity' => 250]);
    $this->latte->recipes()->where('ingredient_id', $this->cup->id)->delete();

    app(SaleService::class)->void($sale, $this->owner, 'Batal');

    expect($this->milk->refresh()->current_stock)->toBe('2000.00')
        ->and($this->cup->refresh()->current_stock)->toBe('50.00');
});

test('sale and void_return movements store the purchase price at that time', function () {
    $sale = checkout($this->cashier, [[$this->latte, 1]]);

    // A later restock at a higher price changes cost_per_unit but not the old sale movement.
    app(StockService::class)->restock($this->coffee, '1000', '300', $this->owner);
    expect($this->coffee->refresh()->cost_per_unit)->toBe('275.23'); // (982 x 250 + 1000 x 300) / 1982

    $saleMovements = $sale->stockMovements()->where('type', StockMovementType::Sale)->get()->keyBy('ingredient_id');
    expect($saleMovements[$this->coffee->id]->unit_cost)->toBe('250.00')
        ->and($saleMovements[$this->milk->id]->unit_cost)->toBe('18.00')
        ->and($saleMovements[$this->cup->id]->unit_cost)->toBe('1200.00');

    $later = checkout($this->cashier, [[$this->americano, 1]]);
    expect($later->stockMovements()->where('ingredient_id', $this->coffee->id)->value('unit_cost'))->toBe('275.23');

    // Void returns at the original price and does not touch the average.
    app(SaleService::class)->void($sale, $this->owner, 'Batal');
    $return = $sale->stockMovements()->where('type', StockMovementType::VoidReturn)->where('ingredient_id', $this->coffee->id)->sole();
    expect($return->unit_cost)->toBe('250.00')
        ->and($this->coffee->refresh()->cost_per_unit)->toBe('275.23');
});

test('a voided sale cannot be voided again', function () {
    $sale = checkout($this->cashier, [[$this->latte, 1]]);
    $service = app(SaleService::class);
    $service->void($sale, $this->owner, 'Batal');
    $movements = StockMovement::count();

    expect(fn () => $service->void(Sale::find($sale->id), $this->owner, 'Lagi'))
        ->toThrow(SaleException::class, "Transaksi {$sale->invoice_no} sudah di-void.");

    // Even a stale model that still says "completed" is rejected (the locked row is checked).
    $stale = Sale::find($sale->id);
    $stale->status = SaleStatus::Completed; // in memory only
    expect(fn () => $service->void($stale, $this->owner, 'Lagi'))->toThrow(SaleException::class);

    expect(StockMovement::count())->toBe($movements)
        ->and($this->milk->refresh()->current_stock)->toBe('2000.00')
        ->and(Sale::find($sale->id)->void_reason)->toBe('Batal');
});

test('owner voids from the sale page with a required reason', function () {
    $sale = checkout($this->cashier, [[$this->latte, 1]]);

    $this->actingAs($this->owner)
        ->post(route('owner.sales.void', $sale), ['reason' => ''])
        ->assertSessionHasErrors(['reason' => 'Alasan wajib diisi.']);
    expect($sale->refresh()->status)->toBe(SaleStatus::Completed);

    $this->actingAs($this->owner)
        ->post(route('owner.sales.void', $sale), ['reason' => 'Pelanggan batal'])
        ->assertRedirect(route('owner.sales.show', $sale))
        ->assertSessionHas('status', "Transaksi {$sale->invoice_no} di-void. Stok bahan sudah dikembalikan.");
    expect($sale->refresh()->status)->toBe(SaleStatus::Void);

    $this->actingAs($this->owner)
        ->post(route('owner.sales.void', $sale), ['reason' => 'Lagi'])
        ->assertRedirect(route('owner.sales.show', $sale))
        ->assertSessionHas('error', "Transaksi {$sale->invoice_no} sudah di-void.");
});

test('void receipts show VOID with the reason for owner and cashier', function () {
    $sale = checkout($this->cashier, [[$this->latte, 1]]);
    app(SaleService::class)->void($sale, $this->owner, 'Pelanggan batal');

    $this->actingAs($this->owner)
        ->get(route('owner.sales.show', $sale))
        ->assertOk()
        ->assertSee(['VOID', 'Alasan: Pelanggan batal', 'Di-void oleh', 'Dimas Pratama'])
        ->assertDontSee('Void transaksi</button>', false);

    $this->actingAs($this->cashier)
        ->get(route('pos.sales.show', $sale))
        ->assertOk()
        ->assertSee(['VOID', 'Alasan: Pelanggan batal']);

    $this->actingAs($this->cashier)->get(route('pos.history'))->assertSee(['Void', $sale->invoice_no]);
});

test('owner sale list filters by date, cashier and status', function () {
    $budi = User::factory()->cashier()->create(['name' => 'Budi Santoso']);

    $this->travelTo(Carbon::parse('2026-10-15 09:00', 'Asia/Jakarta'));
    $old = checkout($this->cashier, [[$this->americano, 1]]);

    $this->travelTo(Carbon::parse('2026-10-17 09:00', 'Asia/Jakarta'));
    $ayu = checkout($this->cashier, [[$this->americano, 1]]);
    $budiSale = checkout($budi, [[$this->americano, 1]]);
    $voided = checkout($budi, [[$this->americano, 1]]);
    app(SaleService::class)->void($voided, $this->owner, 'Batal');

    $list = fn (array $query = []) => $this->actingAs($this->owner)->get(route('owner.sales.index', $query))->assertOk();

    $list()->assertSee([$old->invoice_no, $ayu->invoice_no, $budiSale->invoice_no, $voided->invoice_no]);
    $list(['from' => '2026-10-17', 'to' => '2026-10-17'])->assertSee($ayu->invoice_no)->assertDontSee($old->invoice_no);
    $list(['cashier' => $budi->id])->assertSee([$budiSale->invoice_no, $voided->invoice_no])->assertDontSee($ayu->invoice_no);
    $list(['status' => 'void'])->assertSee($voided->invoice_no)->assertDontSee($budiSale->invoice_no);
    $list(['status' => 'completed', 'cashier' => $budi->id])->assertSee($budiSale->invoice_no)->assertDontSee($voided->invoice_no);
});
