<?php

use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SaleService;

// Real concurrency: see runConcurrently() in tests/Pest.php.

test('parallel voids of the same sale return the stock only once', function () {
    $owner = User::factory()->owner()->create();
    $cashier = User::factory()->cashier()->create();
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $milk = stockedIngredient('Susu UHT', Unit::Ml, 2000);
    $latte = productWithRecipe('Latte', 25000, [[$coffee, 18], [$milk, 150]]);
    $sale = checkout($cashier, [[$latte, 2]]);

    $outcomes = runConcurrently(
        array_fill(0, 4, fn () => app(SaleService::class)->void(Sale::find($sale->id), User::find($owner->id), 'Pelanggan batal')),
        fn ($connection) => $connection->table('sales')->where('id', $sale->id)->lockForUpdate()->first(),
    );

    $voided = array_filter($outcomes, fn ($outcome) => str_starts_with($outcome, 'ok:'));
    $rejected = array_filter($outcomes, fn ($outcome) => $outcome === "rejected:Transaksi {$sale->invoice_no} sudah di-void.");

    expect($voided)->toHaveCount(1, implode(' | ', $outcomes))
        ->and($rejected)->toHaveCount(3)
        ->and(Sale::find($sale->id)->status)->toBe(SaleStatus::Void)
        ->and(Ingredient::find($coffee->id)->current_stock)->toBe('1000.00')
        ->and(Ingredient::find($milk->id)->current_stock)->toBe('2000.00')
        ->and(StockMovement::where('sale_id', $sale->id)->where('type', StockMovementType::VoidReturn)->count())
        ->toBe(StockMovement::where('sale_id', $sale->id)->where('type', StockMovementType::Sale)->count());
});
