<?php

namespace App\Livewire\Pos;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The cashier register: menu grid, cart and payment.
 *
 * The cart only holds product ids, quantities and notes. Prices, availability and stock are
 * always re-read from the database, and the sale itself is made by SaleService::checkout().
 */
class Register extends Component
{
    public string $search = '';

    public ?int $categoryId = null;

    /** @var array<int, array{quantity: int, note: string}> keyed by product id */
    public array $cart = [];

    public string $paymentMethod = 'cash';

    public string $paidAmount = '';

    public ?int $completedSaleId = null;

    #[Computed]
    public function categories(): Collection
    {
        return Category::whereHas('products', fn ($query) => $query->sellable())->orderBy('name')->get();
    }

    /**
     * Sellable products matching the search and category filter, grouped by category name.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, Product>>
     */
    #[Computed]
    public function menu(): \Illuminate\Support\Collection
    {
        return Product::sellable()
            ->with(['category', 'recipes.ingredient'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->categoryId, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->get()
            ->sortBy([['category.name', 'asc'], ['name', 'asc']])
            ->groupBy('category.name');
    }

    /**
     * @return Collection<int, Product> keyed by id
     */
    #[Computed]
    public function cartProducts(): Collection
    {
        return Product::with('recipes.ingredient')->whereIn('id', array_keys($this->cart))->get()->keyBy('id');
    }

    /**
     * Portions still possible per product (menu and cart), after the cart's own usage. A hint only:
     * checkout re-checks the stock under lock.
     *
     * @return array<int, int> product id => portions (negative when the cart already needs too much)
     */
    #[Computed]
    public function availability(): array
    {
        $products = $this->menu->flatten(1)->concat($this->cartProducts->values())->unique('id');

        return app(SaleService::class)->availablePortions(
            new Collection($products->all()),
            array_map(fn (array $line) => (int) $line['quantity'], $this->cart),
        );
    }

    /**
     * Cart lines the stock cannot cover, with the largest quantity it can.
     *
     * @return array<int, int> product id => quantity that still fits
     */
    #[Computed]
    public function lineLimits(): array
    {
        $limits = [];

        foreach ($this->cart as $productId => $line) {
            $more = $this->availability[$productId] ?? 0;
            if ($more < 0) {
                $limits[$productId] = max(0, $line['quantity'] + $more);
            }
        }

        return $limits;
    }

    #[Computed]
    public function total(): string
    {
        $total = '0';

        foreach ($this->cart as $productId => $line) {
            if ($product = $this->cartProducts->get($productId)) {
                $total = bcadd($total, bcmul($product->price, (string) $line['quantity'], 2), 2);
            }
        }

        return $total;
    }

    #[Computed]
    public function itemCount(): int
    {
        return array_sum(array_column($this->cart, 'quantity'));
    }

    /**
     * Amount still missing (positive) or change due (negative) for a cash payment; null when not computable.
     */
    #[Computed]
    public function cashDifference(): ?string
    {
        if (! $this->isValidAmount($this->paidAmount)) {
            return null;
        }

        return bcsub($this->total, $this->paidAmount, 2);
    }

    /**
     * Quick cash buttons: the exact amount plus the next round banknote amounts above the total.
     *
     * @return list<string>
     */
    #[Computed]
    public function quickAmounts(): array
    {
        $total = (int) ceil((float) $this->total);

        if ($total <= 0) {
            return [];
        }

        $amounts = [$total];
        foreach ([10000, 50000, 100000] as $step) {
            $rounded = (int) (ceil($total / $step) * $step);
            if ($rounded > $total) {
                $amounts[] = $rounded;
            }
        }

        $amounts = array_values(array_unique($amounts));
        sort($amounts);

        return array_map('strval', array_slice($amounts, 0, 4));
    }

    #[Computed]
    public function completedSale(): ?Sale
    {
        return $this->completedSaleId
            ? Sale::with(['items', 'user'])->where('user_id', auth()->id())->find($this->completedSaleId)
            : null;
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->categoryId = null;
    }

    public function add(int $productId): void
    {
        $product = Product::sellable()->find($productId);

        if (! $product) {
            return;
        }

        if (($this->availability[$productId] ?? 0) <= 0) {
            $this->addError('menu', "Stok bahan untuk {$product->name} tidak cukup untuk 1 porsi lagi.");

            return;
        }

        $quantity = ($this->cart[$productId]['quantity'] ?? 0) + 1;
        $this->cart[$productId] = [
            'quantity' => min($quantity, SaleService::MAX_QUANTITY),
            'note' => $this->cart[$productId]['note'] ?? '',
        ];

        $this->cartChanged();
    }

    public function increment(int $productId): void
    {
        if (isset($this->cart[$productId])) {
            $this->add($productId);
        }
    }

    /**
     * Quantity typed into a cart line, or picked from the stock hint; 0 removes the line.
     */
    public function setQuantity(int $productId, mixed $quantity): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $quantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 0) {
            $this->cartChanged(); // re-render the field with the last valid quantity

            return;
        }

        if ($quantity === 0) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]['quantity'] = min($quantity, SaleService::MAX_QUANTITY);
        }

        $this->cartChanged();
    }

    /**
     * Enter in the search box: add the first menu item that still has stock, then clear the search.
     */
    public function addFirstMatch(string $search = ''): void
    {
        $this->search = $search;
        unset($this->menu, $this->availability);

        $product = $this->menu->flatten(1)->first(fn (Product $product) => ($this->availability[$product->id] ?? 0) > 0);

        if (! $product) {
            if ($this->menu->isNotEmpty()) {
                $this->addError('menu', 'Stok bahan untuk menu yang dicari tidak cukup.');
            }

            return;
        }

        $this->add($product->id);
        $this->search = '';
        unset($this->menu, $this->availability);
    }

    public function decrement(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId]['quantity'] <= 1) {
            unset($this->cart[$productId]);
        } else {
            $this->cart[$productId]['quantity']--;
        }

        $this->cartChanged();
    }

    public function remove(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->cartChanged();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->paidAmount = '';
        $this->cartChanged();
    }

    public function selectPayment(string $method): void
    {
        if (PaymentMethod::tryFrom($method)) {
            $this->paymentMethod = $method;
            $this->resetErrorBag(['paidAmount', 'checkout']);
        }
    }

    public function setPaidAmount(string $amount): void
    {
        $this->paidAmount = $amount;
        $this->resetErrorBag(['paidAmount', 'checkout']);
    }

    public function checkout(SaleService $sales): void
    {
        $this->validate();

        try {
            $sale = $sales->checkout(
                auth()->user(),
                collect($this->cart)->map(fn (array $line, int $productId) => [
                    'product_id' => $productId,
                    'quantity' => $line['quantity'],
                    'note' => $line['note'] ?? null,
                ])->values()->all(),
                PaymentMethod::from($this->paymentMethod),
                $this->paymentMethod === PaymentMethod::Cash->value ? $this->paidAmount : null,
            );
        } catch (CheckoutException $exception) {
            // Stock may have changed since the page was drawn (another cashier): refresh the hints.
            unset($this->cartProducts, $this->availability, $this->lineLimits);
            $this->addError('checkout', $exception->getMessage());

            return;
        }

        $this->completedSaleId = $sale->id;
        $this->clearCart();
        $this->paymentMethod = PaymentMethod::Cash->value;
        unset($this->completedSale);
    }

    public function newTransaction(): void
    {
        $this->completedSaleId = null;
    }

    public function render()
    {
        return view('livewire.pos.register');
    }

    protected function rules(): array
    {
        return [
            'cart' => ['required', 'array', 'min:1'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:'.SaleService::MAX_QUANTITY],
            'cart.*.note' => ['nullable', 'string', 'max:'.SaleService::MAX_NOTE_LENGTH],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'paidAmount' => [
                Rule::requiredIf($this->paymentMethod === PaymentMethod::Cash->value),
                'nullable',
                'decimal:0,2',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->paymentMethod === PaymentMethod::Cash->value
                        && $this->isValidAmount((string) $value)
                        && bccomp((string) $value, $this->total, 2) < 0) {
                        $fail('Uang diterima kurang dari total '.rupiah($this->total).'.');
                    }
                },
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'cart.required' => 'Keranjang masih kosong.',
            'cart.min' => 'Keranjang masih kosong.',
            'paidAmount.required' => 'Masukkan jumlah uang yang diterima.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'cart.*.quantity' => 'jumlah',
            'cart.*.note' => 'catatan',
            'paymentMethod' => 'metode pembayaran',
            'paidAmount' => 'uang diterima',
        ];
    }

    private function cartChanged(): void
    {
        unset($this->cartProducts, $this->total, $this->itemCount, $this->cashDifference, $this->quickAmounts, $this->availability, $this->lineLimits);
        $this->resetErrorBag();
    }

    private function isValidAmount(string $amount): bool
    {
        return (bool) preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount);
    }
}
