<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\CheckoutException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\SaleException;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public const MAX_QUANTITY = 999;

    public const MAX_NOTE_LENGTH = 100;

    public function __construct(
        private StockService $stock,
        private InvoiceNumberService $invoices,
    ) {}

    /**
     * Save a sale and deduct its ingredients in one database transaction.
     * Nothing is stored when any check fails (PLAN.md rules 2 and 3).
     *
     * Prices, recipes and stock are always read from the database; the cart only says
     * which products, how many, and an optional note per line.
     *
     * @param  array<int, array{product_id: int|string, quantity: int|string, note?: string|null}>  $items
     *
     * @throws CheckoutException
     * @throws InsufficientStockException
     */
    public function checkout(User $cashier, array $items, PaymentMethod $method, ?string $paidAmount = null): Sale
    {
        $lines = $this->normalizeLines($items);

        // One timestamp for the invoice date and created_at, so a sale at midnight cannot get
        // yesterday's number with today's date.
        $now = now();
        $this->invoices->prepareDay($now);

        return DB::transaction(function () use ($cashier, $lines, $method, $paidAmount, $now) {
            $products = Product::sellable()
                ->with('recipes')
                ->whereIn('id', array_keys($lines))
                ->get()
                ->keyBy('id');

            foreach (array_keys($lines) as $productId) {
                if (! $products->has($productId)) {
                    $name = Product::whereKey($productId)->value('name');

                    throw new CheckoutException($name
                        ? "Menu {$name} tidak tersedia untuk dijual."
                        : 'Ada menu di keranjang yang tidak tersedia.');
                }
            }

            // Ingredient usage for the whole cart: recipe quantity x ordered quantity, summed per ingredient.
            $required = [];
            foreach ($lines as $productId => $line) {
                foreach ($products[$productId]->recipes as $recipe) {
                    $required[$recipe->ingredient_id] = bcadd(
                        $required[$recipe->ingredient_id] ?? '0',
                        bcmul($recipe->quantity, (string) $line['quantity'], 2),
                        2,
                    );
                }
            }

            // From here on no other checkout can use these ingredients until this transaction ends.
            $ingredients = $this->stock->lockIngredients(array_keys($required));

            $this->ensureIngredientsStillActive($ingredients, $products);
            $this->ensureEnoughStock($ingredients, $required);

            $total = '0';
            $itemRows = [];
            foreach ($lines as $productId => $line) {
                $product = $products[$productId];
                $subtotal = bcmul($product->price, (string) $line['quantity'], 2);
                $total = bcadd($total, $subtotal, 2);

                $itemRows[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $product->price,
                    'quantity' => $line['quantity'],
                    'subtotal' => $subtotal,
                    'note' => $line['note'],
                ];
            }

            [$paid, $change] = $this->settle($method, $total, $paidAmount);

            $sale = new Sale([
                'invoice_no' => $this->invoices->next($now),
                'user_id' => $cashier->id,
                'total' => $total,
                'payment_method' => $method,
                'paid_amount' => $paid,
                'change_amount' => $change,
                'status' => SaleStatus::Completed,
            ]);
            $sale->created_at = $now;
            $sale->save();

            $sale->items()->createMany($itemRows);

            foreach ($required as $ingredientId => $quantity) {
                $this->stock->recordSaleUsage($ingredients[$ingredientId], $quantity, $sale, $cashier);
            }

            return $sale->load('items');
        });
    }

    /**
     * How many more portions of each product the current stock covers once the cart's own usage is
     * set aside. Negative means the cart already needs more than is on hand: a line of that product
     * fits at most (its quantity + the result) portions.
     *
     * A plain read without locks, for the register's hints only; checkout() re-checks under lock.
     *
     * @param  Collection<int, Product>  $products  with recipes.ingredient loaded
     * @param  array<int, int>  $cart  product id => quantity
     * @return array<int, int> product id => portions
     */
    public function availablePortions(Collection $products, array $cart): array
    {
        $reserved = [];
        $cartRecipes = Recipe::whereIn('product_id', array_keys($cart))->get(['product_id', 'ingredient_id', 'quantity']);
        foreach ($cartRecipes as $recipe) {
            $reserved[$recipe->ingredient_id] = bcadd(
                $reserved[$recipe->ingredient_id] ?? '0',
                bcmul($recipe->quantity, (string) $cart[$recipe->product_id], 2),
                2,
            );
        }

        $portions = [];
        foreach ($products as $product) {
            $limit = null;
            foreach ($product->recipes as $recipe) {
                if (bccomp($recipe->quantity, '0', 2) <= 0) {
                    continue;
                }

                $left = bcsub($recipe->ingredient->current_stock, $reserved[$recipe->ingredient_id] ?? '0', 2);
                $fits = $this->floorDiv($left, $recipe->quantity);
                $limit = $limit === null ? $fits : min($limit, $fits);
            }
            $portions[$product->id] = $limit ?? self::MAX_QUANTITY;
        }

        return $portions;
    }

    /**
     * Cancel a sale: it is kept (status void, reason, voided_at) and every ingredient gets back exactly
     * what the sale took, as void_return movements at the original unit cost (PLAN.md rule 5).
     *
     * The sale row is locked first, so two simultaneous voids queue up and the second one sees the
     * sale already voided.
     *
     * @throws SaleException
     */
    public function void(Sale $sale, User $user, string $reason): Sale
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new SaleException('Alasan void wajib diisi.');
        }

        return DB::transaction(function () use ($sale, $user, $reason) {
            $locked = Sale::whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoid()) {
                throw new SaleException("Transaksi {$locked->invoice_no} sudah di-void.");
            }

            // Return what was actually deducted, not what the (possibly changed) recipe says today.
            $saleMovements = $locked->stockMovements()->where('type', StockMovementType::Sale)->get();
            $ingredients = $this->stock->lockIngredients($saleMovements->pluck('ingredient_id')->unique()->all());

            foreach ($saleMovements->sortBy('ingredient_id') as $movement) {
                $this->stock->recordVoidReturn(
                    $ingredients[$movement->ingredient_id],
                    bcmul($movement->quantity, '-1', 2),
                    $locked,
                    $user,
                    $movement->unit_cost,
                );
            }

            $locked->forceFill([
                'status' => SaleStatus::Void,
                'void_reason' => $reason,
                'voided_at' => now(),
            ])->save();

            $sale->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /**
     * Validate the cart shape and merge lines of the same product.
     *
     * @return array<int, array{quantity: int, note: string|null}> keyed by product id
     */
    private function normalizeLines(array $items): array
    {
        if ($items === []) {
            throw new CheckoutException('Keranjang masih kosong.');
        }

        $lines = [];
        foreach ($items as $item) {
            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $note = trim((string) ($item['note'] ?? ''));

            if ($productId === false || $quantity === false) {
                throw new CheckoutException('Isi keranjang tidak valid.');
            }

            if (mb_strlen($note) > self::MAX_NOTE_LENGTH) {
                throw new CheckoutException('Catatan item maksimal '.self::MAX_NOTE_LENGTH.' karakter.');
            }

            $line = $lines[$productId] ?? ['quantity' => 0, 'note' => null];
            $line['quantity'] += $quantity;
            $line['note'] = collect([$line['note'], $note])->filter()->implode('; ') ?: null;

            if ($line['quantity'] > self::MAX_QUANTITY) {
                throw new CheckoutException('Jumlah per menu maksimal '.self::MAX_QUANTITY.'.');
            }

            $lines[$productId] = $line;
        }

        return $lines;
    }

    /**
     * An ingredient may have been deactivated after the products were loaded; re-check on the locked rows.
     */
    private function ensureIngredientsStillActive(Collection $ingredients, Collection $products): void
    {
        $inactiveIds = $ingredients->where('is_active', false)->keys();

        if ($inactiveIds->isEmpty()) {
            return;
        }

        $product = $products->first(fn (Product $product) => $product->recipes->whereIn('ingredient_id', $inactiveIds)->isNotEmpty());

        throw new CheckoutException("Menu {$product->name} tidak tersedia untuk dijual.");
    }

    /**
     * @param  array<int, string>  $required  ingredient id => quantity needed
     */
    private function ensureEnoughStock(Collection $ingredients, array $required): void
    {
        $shortages = [];

        foreach ($required as $ingredientId => $quantity) {
            $ingredient = $ingredients[$ingredientId];

            if (bccomp($ingredient->current_stock, $quantity, 2) < 0) {
                $shortages[] = [
                    'ingredient' => $ingredient->name,
                    'unit' => $ingredient->unit->label(),
                    'required' => $quantity,
                    'available' => $ingredient->current_stock,
                    'missing' => bcsub($quantity, $ingredient->current_stock, 2),
                ];
            }
        }

        if ($shortages !== []) {
            throw new InsufficientStockException($shortages);
        }
    }

    /**
     * floor($dividend / $divisor) for 2-decimal strings; bcdiv() truncates toward zero instead.
     */
    private function floorDiv(string $dividend, string $divisor): int
    {
        $quotient = (int) bcdiv($dividend, $divisor, 0);

        if (bccomp(bcmul((string) $quotient, $divisor, 2), $dividend, 2) > 0) {
            $quotient--;
        }

        return $quotient;
    }

    /**
     * @return array{0: string, 1: string} [paid amount, change]
     */
    private function settle(PaymentMethod $method, string $total, ?string $paidAmount): array
    {
        if ($method !== PaymentMethod::Cash) {
            return [$total, '0'];
        }

        if ($paidAmount === null || ! preg_match('/^\d{1,10}(\.\d{1,2})?$/', $paidAmount)) {
            throw new CheckoutException('Masukkan jumlah uang yang diterima.');
        }

        if (bccomp($paidAmount, $total, 2) < 0) {
            throw new CheckoutException('Uang diterima ('.rupiah($paidAmount).') kurang dari total ('.rupiah($total).').');
        }

        return [$paidAmount, bcsub($paidAmount, $total, 2)];
    }
}
