<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Real concurrency: see runConcurrently() in tests/Pest.php.

test('parallel checkouts on the same ingredient never oversell it', function () {
    $cashier = User::factory()->cashier()->create();
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 72); // enough for exactly 4 espressos
    $espresso = productWithRecipe('Espresso', 15000, [[$coffee, 18]]);

    $outcomes = runConcurrently(
        array_fill(0, 6, fn () => checkout(User::find($cashier->id), [[$espresso, 1]])),
        fn ($connection) => $connection->table('ingredients')->where('id', $coffee->id)->lockForUpdate()->first(),
    );

    $sold = array_values(array_filter($outcomes, fn ($outcome) => str_starts_with($outcome, 'ok:')));
    $rejected = array_filter($outcomes, fn ($outcome) => $outcome === 'insufficient');

    expect($sold)->toHaveCount(4, implode(' | ', $outcomes))
        ->and($rejected)->toHaveCount(2)
        ->and(Ingredient::find($coffee->id)->current_stock)->toBe('0.00')
        ->and(Ingredient::find($coffee->id)->stockMovements()->sum('quantity'))->toEqual(0)
        ->and(Sale::count())->toBe(4)
        ->and(Sale::pluck('invoice_no')->unique())->toHaveCount(4);
});

test('parallel checkouts get unique, gap-free invoice numbers', function () {
    $cashier = User::factory()->cashier()->create();

    // Separate ingredients so the checkouts only contend on the invoice counter.
    $products = collect(range(1, 5))->map(function (int $i) {
        $ingredient = stockedIngredient("Bahan {$i}", Unit::Pcs, 10);

        return productWithRecipe("Menu {$i}", 15000, [[$ingredient, 1]]);
    });

    // One committed sale first, so today's counter row exists and can be locked by the parent.
    $first = checkout($cashier, [[$products[0], 1]], PaymentMethod::Qris);
    $date = now()->toDateString();

    $outcomes = runConcurrently(
        $products->map(fn ($product) => fn () => checkout(User::find($cashier->id), [[$product, 1]]))->all(),
        fn ($connection) => $connection->table('invoice_sequences')->where('date', $date)->lockForUpdate()->first(),
    );

    $prefix = 'INV-'.now()->format('Ymd').'-';
    $invoices = collect($outcomes)->map(fn ($outcome) => str_replace('ok:', '', $outcome))->sort()->values()->all();

    expect($first->invoice_no)->toBe($prefix.'0001')
        ->and($invoices)->toBe([$prefix.'0002', $prefix.'0003', $prefix.'0004', $prefix.'0005', $prefix.'0006'], implode(' | ', $outcomes))
        ->and(Sale::count())->toBe(6);
});

test('parallel first checkouts of the day create the counter row only once', function () {
    $cashier = User::factory()->cashier()->create();
    $products = collect(range(1, 4))->map(function (int $i) {
        $ingredient = stockedIngredient("Bahan {$i}", Unit::Pcs, 10);

        return productWithRecipe("Menu {$i}", 15000, [[$ingredient, 1]]);
    });
    $date = now()->toDateString();

    expect(DB::table('invoice_sequences')->where('date', $date)->exists())->toBeFalse();

    $outcomes = runConcurrently(
        $products->map(fn ($product) => fn () => checkout(User::find($cashier->id), [[$product, 1]]))->all(),
        // An uncommitted insert of today's row makes every child wait on the same new key; it is rolled back.
        fn ($connection) => $connection->table('invoice_sequences')->insert(['date' => $date, 'last_number' => 0]),
    );

    $prefix = 'INV-'.now()->format('Ymd').'-';
    $invoices = collect($outcomes)->map(fn ($outcome) => str_replace('ok:', '', $outcome))->sort()->values()->all();

    expect($invoices)->toBe([$prefix.'0001', $prefix.'0002', $prefix.'0003', $prefix.'0004'], implode(' | ', $outcomes))
        ->and(DB::table('invoice_sequences')->where('date', $date)->value('last_number'))->toBe(4);
});
