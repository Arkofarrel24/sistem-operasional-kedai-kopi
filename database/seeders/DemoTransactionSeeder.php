<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\StockException;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo history: ~30 days of sales, voids, restocks and waste, all recorded through SaleService and
 * StockService with the clock moved to each event's time, so invoices, stock movements and unit
 * costs are exactly what the app itself would have produced (PLAN.md section 7).
 *
 * Randomness is seeded per date, so seeding on the same date always gives the same data.
 */
class DemoTransactionSeeder extends Seeder
{
    public const DEFAULT_DAYS = 30;

    /** Relative popularity of each product on the POS. */
    private const PRODUCT_WEIGHTS = [
        'Es Kopi Susu Gula Aren' => 30,
        'Americano' => 14,
        'Cappuccino' => 12,
        'Es Teh Manis' => 12,
        'Es Cokelat' => 10,
        'Matcha Latte' => 8,
        'Espresso' => 6,
        'Kopi Susu Aren 1 L' => 3,
    ];

    /** Opening hours 07–20 with morning (07–09) and afternoon (15–17) rushes. */
    private const HOUR_WEIGHTS = [7 => 9, 8 => 10, 9 => 7, 10 => 3, 11 => 3, 12 => 4, 13 => 3, 14 => 3, 15 => 7, 16 => 8, 17 => 7, 18 => 3, 19 => 3, 20 => 2];

    /**
     * Restock policy per ingredient: purchase pack size, base purchase price, and the price after the
     * mid-period increase. Restocked in the morning when stock is below 2x the minimum, up to ~6x.
     */
    private const SUPPLY = [
        'Biji Kopi Arabika' => ['pack' => 5000, 'price' => 250, 'later_price' => 275],
        'Susu UHT' => ['pack' => 12000, 'price' => 18, 'later_price' => 20],
        'Gula Aren Cair' => ['pack' => 2000, 'price' => 40, 'later_price' => 40],
        'Gula Pasir' => ['pack' => 5000, 'price' => 16, 'later_price' => 16],
        'Bubuk Cokelat' => ['pack' => 1000, 'price' => 120, 'later_price' => 125],
        'Bubuk Matcha' => ['pack' => 500, 'price' => 450, 'later_price' => 450],
        'Teh Hitam' => ['pack' => 1000, 'price' => 150, 'later_price' => 150],
        'Es Batu' => ['pack' => 20000, 'price' => 1, 'later_price' => 1],
        'Cup Plastik 16 oz' => ['pack' => 500, 'price' => 1200, 'later_price' => 1250],
        'Botol 1 L' => ['pack' => 50, 'price' => 4500, 'later_price' => 4500],
    ];

    /**
     * Bought "just enough" (see runLowRestockQuantity), so they end the period below their minimum
     * without running out and the dashboard shows low-stock warnings.
     */
    private const RUN_LOW = ['Bubuk Matcha', 'Botol 1 L', 'Teh Hitam'];

    /** [day offset from the start, ingredient, quantity, reason] */
    private const WASTE = [
        [8, 'Susu UHT', 1000, 'Susu basi, kemasan bocor'],
        [13, 'Es Batu', 3000, 'Es mencair, freezer mati semalam'],
        [19, 'Biji Kopi Arabika', 250, 'Biji kopi lembap'],
        [24, 'Susu UHT', 500, 'Tumpah saat membuat pesanan'],
        [27, 'Bubuk Cokelat', 100, 'Kemasan sobek, terkena air'],
    ];

    private const VOID_REASONS = ['Pelanggan batal', 'Salah input menu', 'Pembayaran QRIS gagal', 'Pesanan tercatat dobel'];

    private int $sales = 0;

    private int $voids = 0;

    private int $rejected = 0;

    private int $restocks = 0;

    private int $wastes = 0;

    public function run(SaleService $saleService, StockService $stock, ?CarbonInterface $until = null, int $days = self::DEFAULT_DAYS): void
    {
        $until = CarbonImmutable::instance($until ?? now());
        $start = $until->startOfDay()->subDays($days);

        $owner = User::where('role', Role::Owner)->firstOrFail();
        [$morning, $afternoon] = User::where('role', Role::Cashier)->orderBy('id')->take(2)->get()->all() + [null, null];
        $afternoon ??= $morning;
        $products = Product::sellable()->get()->keyBy('name');
        $ingredients = Ingredient::all()->keyBy('name');

        for ($day = 0; $day <= $days; $day++) {
            $date = $start->addDays($day);
            mt_srand(crc32($date->toDateString()));

            $this->restockMorning($stock, $ingredients, $owner, $date, $day, $days);

            foreach ($this->transactionTimes($date, $until) as $at) {
                $this->sell($saleService, $products, $at->hour < 14 ? $morning : $afternoon, $owner, $at);
            }

            $this->recordWaste($stock, $ingredients, $owner, $date, $day, $until);
        }

        Carbon::setTestNow($until);

        $this->command?->info(sprintf(
            '  %d transaksi (%d void, %d ditolak karena stok), %d restock, %d waste.',
            $this->sales, $this->voids, $this->rejected, $this->restocks, $this->wastes,
        ));
    }

    private function restockMorning(StockService $stock, $ingredients, User $owner, CarbonImmutable $date, int $day, int $days): void
    {
        $this->travel($date->setTime(6, 30));
        $laterHalf = $day >= intdiv($days, 2);

        foreach (self::SUPPLY as $name => $supply) {
            $ingredient = $ingredients[$name]->refresh();
            $quantity = in_array($name, self::RUN_LOW, true)
                ? $this->runLowRestockQuantity($ingredient, $date, $day)
                : $this->regularRestockQuantity($ingredient, $supply['pack']);

            if ($quantity <= 0) {
                continue;
            }

            $price = $laterHalf ? $supply['later_price'] : $supply['price'];
            $stock->restock($ingredient, (string) $quantity, (string) $price, $owner, $this->supplierFor($name));
            $this->restocks++;
        }
    }

    /**
     * Below 2x the minimum: buy whole packs up to about 6x the minimum.
     */
    private function regularRestockQuantity(Ingredient $ingredient, int $pack): int
    {
        $current = (float) $ingredient->current_stock;
        $min = (float) $ingredient->min_stock;

        if ($current >= 2 * $min) {
            return 0;
        }

        return (int) ceil((6 * $min - $current) / $pack) * $pack;
    }

    /**
     * "Buy just enough": only when less than 1.5 days of use is left, about 3 days' worth, never
     * filling above 90% of the minimum. Once the opening stock is used up these ingredients stay
     * below their minimum (low-stock warnings) without running out.
     */
    private function runLowRestockQuantity(Ingredient $ingredient, CarbonImmutable $date, int $day): int
    {
        if ($day === 0) {
            return 0; // no usage history yet; the opening stock covers the first day
        }

        $window = min(7, $day);
        $dailyUse = -1 * (float) $ingredient->stockMovements()
            ->where('type', StockMovementType::Sale)
            ->whereBetween('created_at', [$date->subDays($window), $date])
            ->sum('quantity') / $window;

        $current = (float) $ingredient->current_stock;
        $min = (float) $ingredient->min_stock;

        if ($current >= max(1.5 * $dailyUse, 0.2 * $min)) {
            return 0;
        }

        $needed = min(3 * $dailyUse, 0.9 * $min - $current);
        $step = $ingredient->unit->value === 'pcs' ? 1 : 10;

        return (int) (floor(max($needed, 0) / $step) * $step);
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function transactionTimes(CarbonImmutable $date, CarbonImmutable $until): array
    {
        $weekend = $date->isWeekend();
        $count = (int) round(($weekend ? 50 : 30) * mt_rand(80, 120) / 100);

        $times = [];
        for ($i = 0; $i < $count; $i++) {
            $hour = $this->weighted(self::HOUR_WEIGHTS);
            $at = $date->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

            if ($at->lessThanOrEqualTo($until)) {
                $times[] = $at;
            }
        }

        usort($times, fn (CarbonImmutable $a, CarbonImmutable $b) => $a <=> $b);

        return $times;
    }

    private function sell(SaleService $saleService, $products, User $cashier, User $owner, CarbonImmutable $at): void
    {
        $items = $this->cart($products, $at);
        $total = collect($items)->sum(fn (array $item) => $products->firstWhere('id', $item['product_id'])->price * $item['quantity']);
        [$method, $paid] = $this->payment((int) $total);

        $this->travel($at);

        try {
            $sale = $saleService->checkout($cashier, $items, $method, $paid);
            $this->sales++;
        } catch (InsufficientStockException) {
            $this->rejected++;

            return;
        }

        if (mt_rand(1, 100) <= 2) {
            $this->travel($at->addMinutes(mt_rand(3, 15)));
            $saleService->void($sale, $owner, self::VOID_REASONS[array_rand(self::VOID_REASONS)]);
            $this->voids++;
        }
    }

    /**
     * @return list<array{product_id: int, quantity: int, note: ?string}>
     */
    private function cart($products, CarbonImmutable $at): array
    {
        // Occasional office order in the late morning: one coffee in bulk.
        if ($at->hour >= 9 && $at->hour <= 11 && mt_rand(1, 100) <= 12) {
            $product = $products[mt_rand(0, 1) ? 'Es Kopi Susu Gula Aren' : 'Americano'];

            return [['product_id' => $product->id, 'quantity' => mt_rand(5, 10), 'note' => 'Pesanan kantor']];
        }

        $lines = $this->weighted([1 => 55, 2 => 30, 3 => 15]);
        $weights = array_intersect_key(self::PRODUCT_WEIGHTS, $products->all());
        $items = [];

        while (count($items) < $lines && $weights !== []) {
            $name = $this->weighted($weights);
            unset($weights[$name]);

            $items[] = [
                'product_id' => $products[$name]->id,
                'quantity' => mt_rand(1, 100) <= 80 ? 1 : 2,
                'note' => mt_rand(1, 100) <= 10 ? ['less sugar', 'less ice', 'extra shot', 'tanpa es'][mt_rand(0, 3)] : null,
            ];
        }

        return $items;
    }

    /**
     * @return array{0: PaymentMethod, 1: ?string}
     */
    private function payment(int $total): array
    {
        $roll = mt_rand(1, 100);

        if ($roll > 50) {
            return [$roll > 90 ? PaymentMethod::Transfer : PaymentMethod::Qris, null];
        }

        if (mt_rand(1, 100) <= 40) {
            return [PaymentMethod::Cash, (string) $total];
        }

        $step = [10000, 20000, 50000, 100000][mt_rand(0, 3)];

        return [PaymentMethod::Cash, (string) (int) (ceil(($total + 1) / $step) * $step)];
    }

    private function recordWaste(StockService $stock, $ingredients, User $owner, CarbonImmutable $date, int $day, CarbonImmutable $until): void
    {
        $at = $date->setTime(21, 15);

        foreach (self::WASTE as [$wasteDay, $name, $quantity, $reason]) {
            if ($wasteDay !== $day || $at->greaterThan($until)) {
                continue;
            }

            $this->travel($at);

            try {
                $stock->recordWaste($ingredients[$name], (string) $quantity, $owner, $reason);
                $this->wastes++;
            } catch (StockException) {
                // not enough left to waste that day
            }
        }
    }

    private function supplierFor(string $ingredient): string
    {
        return match ($ingredient) {
            'Biji Kopi Arabika' => 'Roastery Sumber Rejeki',
            'Susu UHT', 'Es Batu' => 'Toko Sembako Makmur',
            'Cup Plastik 16 oz', 'Botol 1 L' => 'Grosir Kemasan Jaya',
            default => 'Toko Bahan Kue Sentosa',
        };
    }

    /**
     * @template T of array-key
     *
     * @param  array<T, int>  $weights
     * @return T
     */
    private function weighted(array $weights): int|string
    {
        $pick = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($pick -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_last($weights);
    }

    private function travel(CarbonInterface $at): void
    {
        Carbon::setTestNow($at);
    }
}
