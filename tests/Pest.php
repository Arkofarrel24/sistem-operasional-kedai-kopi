<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Concurrency tests need committed rows that other database connections/processes can see,
// so they truncate instead of wrapping each test in a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * An ingredient whose opening stock is booked through StockService (so movements stay consistent).
 */
function stockedIngredient(string $name, Unit $unit, string|int $stock, array $attributes = []): Ingredient
{
    $ingredient = Ingredient::factory()->create(['name' => $name, 'unit' => $unit] + $attributes);

    app(StockService::class)->recordInitialStock($ingredient, (string) $stock, User::factory()->owner()->create());

    return $ingredient->refresh();
}

/**
 * @param  list<array{0: Ingredient, 1: string|int}>  $recipe  [ingredient, quantity per portion] pairs
 */
function productWithRecipe(string $name, string|int $price, array $recipe, array $attributes = []): Product
{
    $product = Product::factory()->create(['name' => $name, 'price' => $price] + $attributes);

    foreach ($recipe as [$ingredient, $quantity]) {
        $product->recipes()->create(['ingredient_id' => $ingredient->id, 'quantity' => $quantity]);
    }

    return $product;
}

/**
 * Checkout through SaleService; $lines are [product, quantity] pairs.
 *
 * @param  list<array{0: Product, 1: int}>  $lines
 */
function checkout(User $cashier, array $lines, PaymentMethod $method = PaymentMethod::Qris, ?string $paid = null): Sale
{
    return app(SaleService::class)->checkout(
        $cashier,
        array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        $method,
        $paid,
    );
}
