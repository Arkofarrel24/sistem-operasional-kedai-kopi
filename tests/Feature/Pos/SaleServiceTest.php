<?php

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Exceptions\CheckoutException;
use App\Exceptions\InsufficientStockException;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $this->milk = stockedIngredient('Susu UHT', Unit::Ml, 2000);
    $this->cup = stockedIngredient('Cup', Unit::Pcs, 10);

    $this->esKopiSusu = productWithRecipe('Es Kopi Susu', 22000, [[$this->coffee, 18], [$this->milk, 150], [$this->cup, 1]]);
    $this->americano = productWithRecipe('Americano', 18000, [[$this->coffee, 18], [$this->cup, 1]]);
});

/**
 * Asserts the given callback throws a CheckoutException (or subclass) and stores nothing.
 */
function expectRejected(callable $checkout, string $class, string $message): CheckoutException
{
    $before = [Sale::count(), SaleItem::count(), StockMovement::where('type', StockMovementType::Sale)->count()];
    $stock = Ingredient::pluck('current_stock', 'id')->all();

    try {
        $checkout();
    } catch (CheckoutException $exception) {
        expect($exception)->toBeInstanceOf($class)
            ->and($exception->getMessage())->toContain($message)
            ->and([Sale::count(), SaleItem::count(), StockMovement::where('type', StockMovementType::Sale)->count()])->toBe($before)
            ->and(Ingredient::pluck('current_stock', 'id')->all())->toBe($stock);

        return $exception;
    }

    test()->fail('Checkout was not rejected.');
}

test('checkout deducts recipe x quantity for the whole cart and records sale movements', function () {
    $sale = checkout($this->cashier, [[$this->esKopiSusu, 2], [$this->americano, 3]], PaymentMethod::Cash, '100000');

    // Coffee is used by both products: 18 g x (2 + 3).
    expect($this->coffee->refresh()->current_stock)->toBe('910.00')
        ->and($this->milk->refresh()->current_stock)->toBe('1700.00')
        ->and($this->cup->refresh()->current_stock)->toBe('5.00');

    expect($sale->status)->toBe(SaleStatus::Completed)
        ->and($sale->user_id)->toBe($this->cashier->id)
        ->and($sale->total)->toBe('98000.00')
        ->and($sale->paid_amount)->toBe('100000.00')
        ->and($sale->change_amount)->toBe('2000.00')
        ->and($sale->items)->toHaveCount(2);

    $movements = $sale->stockMovements()->get()->keyBy('ingredient_id');
    expect($movements)->toHaveCount(3)
        ->and($movements[$this->coffee->id]->quantity)->toBe('-90.00')
        ->and($movements[$this->milk->id]->quantity)->toBe('-300.00')
        ->and($movements[$this->cup->id]->quantity)->toBe('-5.00')
        ->and($movements->pluck('type')->unique()->all())->toBe([StockMovementType::Sale])
        ->and($movements->pluck('user_id')->unique()->all())->toBe([$this->cashier->id]);

    // current_stock stays equal to the sum of movements (PLAN.md section 5).
    foreach ([$this->coffee, $this->milk, $this->cup] as $ingredient) {
        expect(bccomp($ingredient->refresh()->current_stock, (string) $ingredient->stockMovements()->sum('quantity'), 2))->toBe(0);
    }
});

test('insufficient stock rejects the sale, names what is short, and stores nothing', function () {
    // 14 x Es Kopi Susu needs 2100 ml milk (have 2000) and 14 cups (have 10).
    $exception = expectRejected(
        fn () => checkout($this->cashier, [[$this->esKopiSusu, 14]]),
        InsufficientStockException::class,
        'Stok tidak cukup:',
    );

    expect($exception->getMessage())->toContain('Susu UHT kurang 100 ml')
        ->and($exception->getMessage())->toContain('Cup kurang 4 pcs')
        ->and($exception->getMessage())->not->toContain('Biji Kopi')
        ->and(DB::table('invoice_sequences')->sum('last_number'))->toEqual(0);

    // The rejected attempt did not consume an invoice number.
    expect(checkout($this->cashier, [[$this->esKopiSusu, 1]])->invoice_no)->toEndWith('-0001');
});

test('stock exactly matching the requirement is enough', function () {
    checkout($this->cashier, [[$this->americano, 10]]);

    expect($this->cup->refresh()->current_stock)->toBe('0.00');
});

test('sale items keep the name and price snapshot after the product changes', function () {
    $sale = checkout($this->cashier, [[$this->esKopiSusu, 2]]);

    $this->esKopiSusu->update(['name' => 'Es Kopi Susu Spesial', 'price' => 30000]);

    $item = $sale->items()->sole();
    expect($item->product_name)->toBe('Es Kopi Susu')
        ->and($item->unit_price)->toBe('22000.00')
        ->and($item->subtotal)->toBe('44000.00')
        ->and($item->quantity)->toBe(2)
        ->and($sale->refresh()->total)->toBe('44000.00');
});

test('invoice numbers are sequential per day and reset on the next Jakarta day', function () {
    $this->travelTo(Carbon::parse('2026-10-17 08:00', 'Asia/Jakarta'));
    $first = checkout($this->cashier, [[$this->americano, 1]]);
    $second = checkout($this->cashier, [[$this->americano, 1]]);

    // 23:30 WIB is still 17 Oct.
    $this->travelTo(Carbon::parse('2026-10-17 23:30', 'Asia/Jakarta'));
    $third = checkout($this->cashier, [[$this->americano, 1]]);

    // 00:05 WIB on 18 Oct is still 17 Oct in UTC; numbering must follow the Jakarta date.
    $this->travelTo(Carbon::parse('2026-10-18 00:05', 'Asia/Jakarta'));
    $nextDay = checkout($this->cashier, [[$this->americano, 1]]);
    $nextDaySecond = checkout($this->cashier, [[$this->americano, 1]]);

    expect([$first, $second, $third, $nextDay, $nextDaySecond])->sequence(
        fn ($sale) => $sale->invoice_no->toBe('INV-20261017-0001'),
        fn ($sale) => $sale->invoice_no->toBe('INV-20261017-0002'),
        fn ($sale) => $sale->invoice_no->toBe('INV-20261017-0003'),
        fn ($sale) => $sale->invoice_no->toBe('INV-20261018-0001'),
        fn ($sale) => $sale->invoice_no->toBe('INV-20261018-0002'),
    );
});

test('the database rejects a duplicate invoice number', function () {
    $sale = checkout($this->cashier, [[$this->americano, 1]]);

    Sale::create($sale->only(['user_id', 'total', 'payment_method', 'paid_amount', 'change_amount', 'status']) + ['invoice_no' => $sale->invoice_no]);
})->throws(UniqueConstraintViolationException::class);

test('cash payment below the total is rejected', function () {
    expectRejected(
        fn () => checkout($this->cashier, [[$this->esKopiSusu, 1]], PaymentMethod::Cash, '20000'),
        CheckoutException::class,
        'Uang diterima (Rp20.000) kurang dari total (Rp22.000).',
    );

    expectRejected(
        fn () => checkout($this->cashier, [[$this->esKopiSusu, 1]], PaymentMethod::Cash, null),
        CheckoutException::class,
        'Masukkan jumlah uang yang diterima.',
    );

    expectRejected(
        fn () => checkout($this->cashier, [[$this->esKopiSusu, 1]], PaymentMethod::Cash, '1e9'),
        CheckoutException::class,
        'Masukkan jumlah uang yang diterima.',
    );
});

test('exact cash gives no change and non-cash payments are recorded as paid in full', function () {
    $cash = checkout($this->cashier, [[$this->esKopiSusu, 1]], PaymentMethod::Cash, '22000');
    expect($cash->paid_amount)->toBe('22000.00')->and($cash->change_amount)->toBe('0.00');

    foreach ([PaymentMethod::Qris, PaymentMethod::Transfer] as $method) {
        // A paid amount sent along with a non-cash payment is ignored.
        $sale = checkout($this->cashier, [[$this->esKopiSusu, 1]], $method, '50000');

        expect($sale->payment_method)->toBe($method)
            ->and($sale->paid_amount)->toBe('22000.00')
            ->and($sale->change_amount)->toBe('0.00');
    }
});

test('products without a recipe, inactive products and products with an inactive ingredient cannot be sold', function () {
    $noRecipe = Product::factory()->create(['name' => 'Kopi Tubruk']);
    $inactive = productWithRecipe('Teh Tarik', 18000, [[$this->milk, 100]], ['is_active' => false]);
    $caramel = stockedIngredient('Sirup Karamel', Unit::Ml, 1000, ['is_active' => false]);
    $caramelLatte = productWithRecipe('Caramel Latte', 28000, [[$this->coffee, 18], [$caramel, 20]]);

    foreach ([$noRecipe, $inactive, $caramelLatte] as $product) {
        expectRejected(
            fn () => checkout($this->cashier, [[$this->americano, 1], [$product, 1]]),
            CheckoutException::class,
            "Menu {$product->name} tidak tersedia untuk dijual.",
        );
    }

    expectRejected(
        fn () => app(SaleService::class)->checkout($this->cashier, [['product_id' => 999999, 'quantity' => 1]], PaymentMethod::Qris),
        CheckoutException::class,
        'Ada menu di keranjang yang tidak tersedia.',
    );
});

test('malformed carts are rejected', function (array $items, string $message) {
    expectRejected(
        fn () => app(SaleService::class)->checkout($this->cashier, $items, PaymentMethod::Qris),
        CheckoutException::class,
        $message,
    );
})->with([
    'empty cart' => [[], 'Keranjang masih kosong.'],
    'zero quantity' => [[['product_id' => 1, 'quantity' => 0]], 'Isi keranjang tidak valid.'],
    'fractional quantity' => [[['product_id' => 1, 'quantity' => '1.5']], 'Isi keranjang tidak valid.'],
    'quantity too large' => [[['product_id' => 1, 'quantity' => 1000]], 'Jumlah per menu maksimal 999.'],
    'note too long' => [[['product_id' => 1, 'quantity' => 1, 'note' => str_repeat('a', 101)]], 'Catatan item maksimal 100 karakter.'],
]);

test('lines of the same product are merged with their notes', function () {
    $sale = app(SaleService::class)->checkout($this->cashier, [
        ['product_id' => $this->esKopiSusu->id, 'quantity' => 1, 'note' => 'less sugar'],
        ['product_id' => $this->esKopiSusu->id, 'quantity' => 2, 'note' => 'nama: Budi'],
    ], PaymentMethod::Qris);

    $item = $sale->items()->sole();
    expect($item->quantity)->toBe(3)
        ->and($item->note)->toBe('less sugar; nama: Budi')
        ->and($this->milk->refresh()->current_stock)->toBe('1550.00');
});
