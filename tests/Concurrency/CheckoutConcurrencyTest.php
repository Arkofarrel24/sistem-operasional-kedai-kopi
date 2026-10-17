<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * Real concurrency: every checkout runs in its own forked PHP process with its own MySQL
 * connection, against committed data. The parent first holds a lock on the contended row so
 * that all children are guaranteed to be inside checkout() at the same time, then releases it.
 */

beforeEach(function () {
    if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
        $this->markTestSkipped('pcntl and posix extensions are required.');
    }

    $this->outcomes = storage_path('framework/testing/concurrency-'.uniqid());
    File::ensureDirectoryExists($this->outcomes);
});

afterEach(function () {
    File::deleteDirectory($this->outcomes);
});

/**
 * Fork one child per checkout; each waits for the go signal, checks out, writes its outcome and dies.
 *
 * @param  list<callable(): mixed>  $checkouts
 * @param  callable(Connection): void  $holdLock  runs in the parent before the go signal
 * @return list<string> outcomes: "ok:<invoice>", "insufficient" or "error:<message>"
 */
function runConcurrently(string $dir, array $checkouts, callable $holdLock): array
{
    $go = "{$dir}/go";

    // Children must never share the parent's MySQL socket.
    DB::disconnect();

    $children = [];
    foreach ($checkouts as $index => $checkout) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            $outcome = 'error:not started';
            try {
                while (! file_exists($go)) {
                    usleep(5_000);
                }
                $outcome = 'ok:'.$checkout()->invoice_no;
            } catch (InsufficientStockException) {
                $outcome = 'insufficient';
            } catch (Throwable $exception) {
                $outcome = 'error:'.$exception->getMessage();
            }
            file_put_contents("{$dir}/{$index}", $outcome);
            posix_kill(getmypid(), SIGKILL); // skip PHPUnit/Laravel shutdown handlers in the child
        }

        $children[] = $pid;
    }

    // Parent: take the contended lock on a separate connection, release the children, let them pile up.
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

    return collect($checkouts)->keys()->map(fn ($index) => @file_get_contents("{$dir}/{$index}") ?: 'error:no outcome')->all();
}

test('parallel checkouts on the same ingredient never oversell it', function () {
    $cashier = User::factory()->cashier()->create();
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 72); // enough for exactly 4 espressos
    $espresso = productWithRecipe('Espresso', 15000, [[$coffee, 18]]);

    $outcomes = runConcurrently(
        $this->outcomes,
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
        $this->outcomes,
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
        $this->outcomes,
        $products->map(fn ($product) => fn () => checkout(User::find($cashier->id), [[$product, 1]]))->all(),
        // An uncommitted insert of today's row makes every child wait on the same new key; it is rolled back.
        fn ($connection) => $connection->table('invoice_sequences')->insert(['date' => $date, 'last_number' => 0]),
    );

    $prefix = 'INV-'.now()->format('Ymd').'-';
    $invoices = collect($outcomes)->map(fn ($outcome) => str_replace('ok:', '', $outcome))->sort()->values()->all();

    expect($invoices)->toBe([$prefix.'0001', $prefix.'0002', $prefix.'0003', $prefix.'0004'], implode(' | ', $outcomes))
        ->and(DB::table('invoice_sequences')->where('date', $date)->value('last_number'))->toBe(4);
});
