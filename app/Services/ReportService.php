<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Ingredient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales figures for the dashboard, reports and the Excel export (PLAN.md rules 12–14):
 * - only completed sales count; voided sales are excluded everywhere,
 * - days and hours are Asia/Jakarta (timestamps are stored in Jakarta time and the DB session runs at +07:00),
 *   and a date range includes its whole end date,
 * - every figure is aggregated in SQL (SUM/COUNT/GROUP BY); money stays a decimal string (bcmath).
 */
class ReportService
{
    /**
     * @return array{revenue: string, transactions: int, average: string, by_method: list<array{method: PaymentMethod, transactions: int, revenue: string}>}
     */
    public function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->summaryBetween(...$this->bounds($from, $to));
    }

    /**
     * Same as summary() for an exact time window, e.g. today until now versus the same window last week.
     *
     * @return array{revenue: string, transactions: int, average: string, by_method: list<array{method: PaymentMethod, transactions: int, revenue: string}>}
     */
    public function summaryBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $sales = fn () => DB::table('sales')
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('created_at', [$start, $end]);

        $totals = $sales()
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->first();

        $byMethod = $sales()
            ->selectRaw('payment_method, COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $transactions = (int) $totals->transactions;
        $revenue = $this->money($totals->revenue);

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'average' => $transactions > 0 ? $this->round(bcdiv($revenue, (string) $transactions, 4)) : '0.00',
            'by_method' => array_map(fn (PaymentMethod $method) => [
                'method' => $method,
                'transactions' => (int) ($byMethod[$method->value]->transactions ?? 0),
                'revenue' => $this->money($byMethod[$method->value]->revenue ?? 0),
            ], PaymentMethod::cases()),
        ];
    }

    /**
     * Revenue per Jakarta day, including days without sales.
     *
     * @return list<array{date: string, revenue: string, transactions: int}>
     */
    public function dailyRevenue(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->completedSales($from, $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('day');

        $days = [];
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'revenue' => $this->money($rows[$key]->revenue ?? 0),
                'transactions' => (int) ($rows[$key]->transactions ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Sales per hour of the day (0–23, Jakarta), summed over the range.
     *
     * @return list<array{hour: int, transactions: int, revenue: string}>
     */
    public function hourly(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->completedSales($from, $to)
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw('HOUR(created_at)')
            ->get()
            ->keyBy('hour');

        return array_map(fn (int $hour) => [
            'hour' => $hour,
            'transactions' => (int) ($rows[$hour]->transactions ?? 0),
            'revenue' => $this->money($rows[$hour]->revenue ?? 0),
        ], range(0, 23));
    }

    /**
     * Best sellers by revenue (then quantity), using the sale-time price snapshot and today's product name.
     *
     * @return Collection<int, object{product_id: int, name: string, quantity: int, revenue: string}>
     */
    public function topProducts(CarbonInterface $from, CarbonInterface $to, ?int $limit = null): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.created_at', $this->bounds($from, $to))
            ->groupBy('sale_items.product_id', 'products.name')
            ->selectRaw('sale_items.product_id, products.name, SUM(sale_items.quantity) as quantity, SUM(sale_items.subtotal) as revenue')
            ->orderByDesc('revenue')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->when($limit, fn (Builder $query, int $limit) => $query->limit($limit))
            ->get()
            ->map(fn (object $row) => (object) [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => $this->money($row->revenue),
            ]);
    }

    /**
     * Revenue, cost of goods sold and gross profit. COGS uses the unit_cost stored on each sale movement
     * at checkout time, never today's cost_per_unit, so old periods keep their historical cost.
     *
     * @return array{revenue: string, cogs: string, gross_profit: string, margin: ?string}
     */
    public function grossProfit(CarbonInterface $from, CarbonInterface $to): array
    {
        $revenue = $this->summary($from, $to)['revenue'];

        $cogs = DB::table('stock_movements')
            ->join('sales', 'sales.id', '=', 'stock_movements.sale_id')
            ->where('stock_movements.type', StockMovementType::Sale->value)
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.created_at', $this->bounds($from, $to))
            ->selectRaw('COALESCE(SUM(-stock_movements.quantity * COALESCE(stock_movements.unit_cost, 0)), 0) as cogs')
            ->value('cogs');

        $cogs = $this->round((string) $cogs);
        $grossProfit = bcsub($revenue, $cogs, 2);

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            // fraction, e.g. "0.6532" = 65.32 %
            'margin' => bccomp($revenue, '0', 2) > 0 ? bcdiv($grossProfit, $revenue, 4) : null,
        ];
    }

    /**
     * Value of wasted stock at the purchase price recorded on each waste movement.
     *
     * @return array{total: string, items: Collection<int, object{name: string, unit: string, quantity: string, value: string}>}
     */
    public function waste(CarbonInterface $from, CarbonInterface $to): array
    {
        $items = DB::table('stock_movements')
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')
            ->where('stock_movements.type', StockMovementType::Waste->value)
            ->whereBetween('stock_movements.created_at', $this->bounds($from, $to))
            ->groupBy('stock_movements.ingredient_id', 'ingredients.name', 'ingredients.unit')
            ->selectRaw('ingredients.name, ingredients.unit, SUM(-stock_movements.quantity) as quantity, SUM(-stock_movements.quantity * COALESCE(stock_movements.unit_cost, 0)) as value')
            ->orderByDesc('value')
            ->get()
            ->map(fn (object $row) => (object) [
                'name' => $row->name,
                'unit' => $row->unit,
                'quantity' => $this->money($row->quantity),
                'value' => $this->round((string) $row->value),
            ]);

        return [
            'total' => $items->reduce(fn (string $sum, object $item) => bcadd($sum, $item->value, 2), '0.00'),
            'items' => $items,
        ];
    }

    /**
     * Ingredients that are low (below minimum) or out of stock, emptiest first.
     *
     * @return EloquentCollection<int, Ingredient>
     */
    public function lowStockIngredients(): EloquentCollection
    {
        return Ingredient::query()
            ->where(fn ($query) => $query->whereColumn('current_stock', '<', 'min_stock')->orWhere('current_stock', '<=', 0))
            ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 ELSE 1 END')
            ->orderByRaw('current_stock / NULLIF(min_stock, 0)')
            ->orderBy('name')
            ->get();
    }

    /**
     * Inclusive datetime bounds for a range of Jakarta dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function bounds(CarbonInterface $from, CarbonInterface $to): array
    {
        $timezone = config('app.timezone');

        return [
            CarbonImmutable::parse($from->toDateString(), $timezone)->startOfDay(),
            CarbonImmutable::parse($to->toDateString(), $timezone)->endOfDay(),
        ];
    }

    private function completedSales(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return DB::table('sales')
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('created_at', $this->bounds($from, $to));
    }

    private function money(string|int|float|null $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }

    /**
     * Round half-up to 2 decimals (SQL products of two decimal(12,2) columns have 4 decimals).
     */
    private function round(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd(bcadd($value, $offset, 6), '0', 2);
    }
}
