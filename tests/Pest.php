<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleException;
use App\Exceptions\StockException;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
    ->beforeEach(function () {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl and posix extensions are required.');
        }
    })
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

/**
 * Real concurrency: run each task in its own forked PHP process with its own MySQL connection,
 * against committed data. The parent first holds a lock on the contended row (via $holdLock on a
 * separate connection) so all children are guaranteed to be inside the operation at the same
 * time, then releases it and waits for them.
 *
 * @param  list<callable(): mixed>  $tasks
 * @param  callable(Connection): void  $holdLock
 * @return list<string> per task: "ok:<invoice or result>", "insufficient", "rejected:<message>" or "error:<message>"
 */
function runConcurrently(array $tasks, callable $holdLock): array
{
    $dir = storage_path('framework/testing/concurrency-'.uniqid());
    File::ensureDirectoryExists($dir);
    $go = "{$dir}/go";

    // Children must never share the parent's MySQL socket.
    DB::disconnect();

    $children = [];
    foreach ($tasks as $index => $task) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $outcome = 'error:not started';
            try {
                while (! file_exists($go)) {
                    usleep(5_000);
                }
                $result = $task();
                $outcome = 'ok:'.($result instanceof Sale ? $result->invoice_no : (string) $result);
            } catch (InsufficientStockException) {
                $outcome = 'insufficient';
            } catch (SaleException|StockException $exception) {
                $outcome = 'rejected:'.$exception->getMessage();
            } catch (Throwable $exception) {
                $outcome = 'error:'.$exception->getMessage();
            }
            file_put_contents("{$dir}/{$index}", $outcome);
            posix_kill(getmypid(), SIGKILL); // skip PHPUnit/Laravel shutdown handlers in the child
        }

        $children[] = $pid;
    }

    config(['database.connections.lock_holder' => config('database.connections.'.config('database.default'))]);
    $lockHolder = DB::connection('lock_holder');
    $lockHolder->beginTransaction();
    $holdLock($lockHolder);
    touch($go);
    usleep(1_000_000);
    $lockHolder->rollBack();
    DB::purge('lock_holder');

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $outcomes = collect($tasks)->keys()->map(fn ($index) => @file_get_contents("{$dir}/{$index}") ?: 'error:no outcome')->all();
    File::deleteDirectory($dir);

    return $outcomes;
}
