<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->cashier = User::factory()->cashier()->create();

    $this->travelTo(Carbon::parse('2026-10-01 06:00', 'Asia/Jakarta'));
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 10000, ['min_stock' => 1000]);
    $this->latte = productWithRecipe('Latte', 25000, [[$coffee, 18]]);
    $this->americano = productWithRecipe('Americano', 18000, [[$coffee, 18]]);

    $sell = function (string $time, array $lines) {
        $this->travelTo(Carbon::parse($time, 'Asia/Jakarta'));
        checkout($this->cashier, $lines, PaymentMethod::Qris);
    };

    // Last week, Thursday 8 Oct: one sale before 14:00, one after (not part of the comparison).
    $sell('2026-10-08 10:00', [[$this->latte, 1]]);
    $sell('2026-10-08 18:00', [[$this->latte, 1]]);
    // This week (Monday 12 Oct).
    $sell('2026-10-12 10:00', [[$this->americano, 3]]);
    // Today, Thursday 15 Oct, until 14:00.
    $sell('2026-10-15 09:00', [[$this->latte, 1]]);
    $sell('2026-10-15 13:00', [[$this->americano, 1]]);

    $this->travelTo(Carbon::parse('2026-10-15 14:00', 'Asia/Jakarta'));
});

test('dashboard compares today with the same weekday last week up to the same time', function () {
    $this->actingAs($this->owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertSee('Dibandingkan Kamis, 8 Okt sampai pukul 14.00')
        // revenue 43.000 vs 25.000 (+72,0 %), transactions 2 vs 1 (+100 %), average 21.500 vs 25.000 (-14,0 %)
        ->assertSeeInOrder(['Omzet', 'Rp43.000', 'Naik', '72,0%', 'minggu lalu', 'Rp25.000'])
        ->assertSeeInOrder(['Transaksi', '2', 'Naik', '100,0%', 'minggu lalu', '1'])
        ->assertSeeInOrder(['Rata-rata per transaksi', 'Rp21.500', 'Turun', '14,0%', 'minggu lalu', 'Rp25.000']);
});

test('dashboard shows no percentage when last week had no sales', function () {
    $this->travelTo(Carbon::parse('2026-10-15 08:00', 'Asia/Jakarta'));

    $this->actingAs($this->owner)->get(route('owner.dashboard'))
        ->assertSee('Minggu lalu belum ada transaksi');
});

test('dashboard lists low and out of stock ingredients with restock links', function () {
    $syrup = stockedIngredient('Sirup Karamel', Unit::Ml, 50, ['min_stock' => 200]);
    $bottle = stockedIngredient('Botol 1 L', Unit::Pcs, 0, ['min_stock' => 10]);
    stockedIngredient('Gula Pasir', Unit::Gram, 5000, ['min_stock' => 1000]);

    $this->actingAs($this->owner)->get(route('owner.dashboard'))
        ->assertSeeInOrder(['Perlu restock', '2 bahan', 'Botol 1 L', 'Habis', '0 dari minimum 10 pcs', 'Sirup Karamel', 'Menipis', '50 dari minimum 200 ml'])
        ->assertSee(route('owner.stock.restock.create', $bottle))
        ->assertSee(route('owner.stock.restock.create', $syrup))
        ->assertDontSee('Gula Pasir');
});

test('dashboard shows when no ingredient needs restocking', function () {
    $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertSee('Semua bahan di atas stok minimum.');
});

test('dashboard top products cover this week from Monday', function () {
    $this->actingAs($this->owner)->get(route('owner.dashboard'))
        ->assertSee('12 Okt sampai 15 Okt 2026')
        ->assertViewHas('topProducts', fn ($top) => $top->map(fn ($p) => [$p->name, $p->quantity, $p->revenue])->all() === [
            ['Americano', 4, '72000.00'],
            ['Latte', 1, '25000.00'], // last Thursday's lattes are not this week
        ]);
});

test('dashboard chart holds the last 30 days', function () {
    $this->actingAs($this->owner)->get(route('owner.dashboard'))
        ->assertSee('data-revenue-chart=', false)
        ->assertViewHas('chart', function (array $chart) {
            $last = end($chart);
            $byLabel = collect($chart)->keyBy('label');

            return count($chart) === 30
                && $chart[0]['label'] === '16 Sep'
                && $last === ['label' => '15 Okt', 'revenue' => 43000.0, 'transactions' => 2]
                && $byLabel['8 Okt']['revenue'] === 50000.0;
        });
});

test('dashboard chart switch is a keyboard-reachable radio group', function () {
    $this->actingAs($this->owner)->get(route('owner.dashboard'))
        ->assertSee('role="radiogroup"', false)
        ->assertSee('data-segmented data-selected="0"', false)
        ->assertSeeInOrder(['data-range="7"', 'aria-checked="true"', 'data-range="30"', 'aria-checked="false"'], false)
        // 7-day summary rendered before JavaScript runs: 9-15 Oct = 54.000 (12 Oct) + 43.000 (today).
        ->assertSeeInOrder(['Rp97.000', 'dalam', '7', 'hari, rata-rata', 'Rp13.857', 'per hari']);
});
