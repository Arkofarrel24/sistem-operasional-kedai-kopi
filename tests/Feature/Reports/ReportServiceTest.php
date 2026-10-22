<?php

use App\Enums\PaymentMethod;
use App\Services\ReportService;
use App\Services\StockService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

require_once __DIR__.'/ReportScenario.php';

beforeEach(function () {
    $this->data = seedReportScenario();
    $this->reports = app(ReportService::class);
    $this->day = fn (string $date) => CarbonImmutable::parse($date, 'Asia/Jakarta');
});

test('summary counts completed sales only, per payment method', function () {
    $summary = $this->reports->summary(($this->day)('2026-10-01'), ($this->day)('2026-10-01'));

    expect($summary['revenue'])->toBe('147000.00')
        ->and($summary['transactions'])->toBe(4)
        ->and($summary['average'])->toBe('36750.00')
        ->and(collect($summary['by_method'])->map(fn ($row) => [$row['method'], $row['transactions'], $row['revenue']])->all())->toBe([
            [PaymentMethod::Cash, 2, '86000.00'],
            [PaymentMethod::Qris, 1, '36000.00'],      // the voided Rp75.000 QRIS sale is not counted
            [PaymentMethod::Transfer, 1, '25000.00'],
        ]);
});

test('date ranges include the whole end date in Jakarta time', function () {
    // 1 Oct includes 23:50 but neither 30 Sep 23:59 nor 2 Oct 00:10.
    expect($this->reports->summary(($this->day)('2026-10-01'), ($this->day)('2026-10-01'))['transactions'])->toBe(4)
        ->and($this->reports->summary(($this->day)('2026-10-01'), ($this->day)('2026-10-02'))['revenue'])->toBe('172000.00')
        ->and($this->reports->summary(($this->day)('2026-10-02'), ($this->day)('2026-10-02'))['revenue'])->toBe('25000.00')
        ->and($this->reports->summary(($this->day)('2026-09-30'), ($this->day)('2026-09-30'))['revenue'])->toBe('18000.00')
        ->and($this->reports->summary(($this->day)('2026-10-03'), ($this->day)('2026-10-05'))['transactions'])->toBe(0);
});

test('sales per hour group by Jakarta hour, including sales near midnight', function () {
    $hours = collect($this->reports->hourly(($this->day)('2026-10-01'), ($this->day)('2026-10-02')))->keyBy('hour');

    expect($hours)->toHaveCount(24)
        ->and($hours[8])->toBe(['hour' => 8, 'transactions' => 2, 'revenue' => '104000.00'])
        ->and($hours[15])->toBe(['hour' => 15, 'transactions' => 1, 'revenue' => '25000.00'])
        ->and($hours[16]['transactions'])->toBe(0) // voided
        ->and($hours[23])->toBe(['hour' => 23, 'transactions' => 1, 'revenue' => '18000.00'])
        ->and($hours[0])->toBe(['hour' => 0, 'transactions' => 1, 'revenue' => '25000.00'])
        ->and($hours->sum('transactions'))->toBe(5);
});

test('best sellers by revenue use the sale-time price and skip voided sales', function () {
    $top = $this->reports->topProducts(($this->day)('2026-10-01'), ($this->day)('2026-10-02'));

    expect($top->map(fn ($row) => [$row->name, $row->quantity, $row->revenue])->all())->toBe([
        ['Latte', 4, '100000.00'],     // 2 + 1 + 1 (the voided 3 are excluded)
        ['Americano', 4, '72000.00'],  // 1 + 2 + 1
    ]);

    // A later price change does not touch past revenue.
    $this->data['latte']->update(['price' => 30000]);
    expect($this->reports->topProducts(($this->day)('2026-10-01'), ($this->day)('2026-10-02'), 1)->sole()->revenue)->toBe('100000.00');
});

test('gross profit uses the historical unit cost, even after a restock raises the price', function () {
    $oct1 = fn () => $this->reports->grossProfit(($this->day)('2026-10-01'), ($this->day)('2026-10-01'));

    expect($oct1())->toBe(['revenue' => '147000.00', 'cogs' => '48000.00', 'gross_profit' => '99000.00', 'margin' => '0.6734']);

    // Coffee left: 10.000 - 18 x 9 portions sold (voided ones returned) - 100 wasted = 9.738 g.
    $this->travelTo(Carbon::parse('2026-10-03 07:00', 'Asia/Jakarta'));
    app(StockService::class)->restock($this->data['coffee'], '10000', '300', $this->data['owner']);
    expect($this->data['coffee']->refresh()->cost_per_unit)->toBe('275.33'); // (9738 x 250 + 10000 x 300) / 19738

    $this->travelTo(Carbon::parse('2026-10-03 09:00', 'Asia/Jakarta'));
    checkout($this->data['cashier'], [[$this->data['americano'], 1]]);

    expect($oct1()['cogs'])->toBe('48000.00') // unchanged
        ->and($this->reports->grossProfit(($this->day)('2026-10-03'), ($this->day)('2026-10-03')))->toBe([
            'revenue' => '18000.00',
            'cogs' => '6155.94', // 18 x 275.33 + 1.200
            'gross_profit' => '11844.06',
            'margin' => '0.6580',
        ])
        ->and($this->reports->grossProfit(($this->day)('2026-10-01'), ($this->day)('2026-10-02'))['cogs'])->toBe('56400.00');
});

test('gross profit of a period without sales has no margin', function () {
    expect($this->reports->grossProfit(($this->day)('2026-10-10'), ($this->day)('2026-10-10')))
        ->toBe(['revenue' => '0.00', 'cogs' => '0.00', 'gross_profit' => '0.00', 'margin' => null]);
});

test('waste value uses the unit cost recorded on each waste movement', function () {
    $oct1 = $this->reports->waste(($this->day)('2026-10-01'), ($this->day)('2026-10-01'));
    $both = $this->reports->waste(($this->day)('2026-10-01'), ($this->day)('2026-10-02'));

    expect($oct1['total'])->toBe('9000.00')
        ->and($both['total'])->toBe('34000.00')
        ->and($both['items']->map(fn ($item) => [$item->name, $item->quantity, $item->value])->all())->toBe([
            ['Biji Kopi', '100.00', '25000.00'],
            ['Susu UHT', '500.00', '9000.00'],
        ]);
});

test('daily revenue lists every day of the range, with zero for days without sales', function () {
    expect($this->reports->dailyRevenue(($this->day)('2026-09-29'), ($this->day)('2026-10-03')))->toBe([
        ['date' => '2026-09-29', 'revenue' => '0.00', 'transactions' => 0],
        ['date' => '2026-09-30', 'revenue' => '18000.00', 'transactions' => 1],
        ['date' => '2026-10-01', 'revenue' => '147000.00', 'transactions' => 4],
        ['date' => '2026-10-02', 'revenue' => '25000.00', 'transactions' => 1],
        ['date' => '2026-10-03', 'revenue' => '0.00', 'transactions' => 0],
    ]);
});
