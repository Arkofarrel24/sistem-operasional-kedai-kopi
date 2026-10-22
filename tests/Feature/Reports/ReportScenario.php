<?php

use App\Enums\PaymentMethod;
use App\Enums\Unit;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Carbon\Carbon;

/**
 * Hand-computed report scenario (Asia/Jakarta times).
 *
 * Cost per portion at the opening prices (coffee Rp250/g, milk Rp18/ml, cup Rp1.200):
 *   Latte     Rp25.000 = 18 g coffee + 150 ml milk + 1 cup = 4.500 + 2.700 + 1.200 = Rp8.400
 *   Americano Rp18.000 = 18 g coffee + 1 cup               = 4.500 + 1.200         = Rp5.700
 *
 *   30 Sep 23:59  cash      Americano x1                    18.000  (cost 5.700)
 *    1 Oct 08:15  cash      Latte x2 + Americano x1         68.000  (cost 22.500)
 *    1 Oct 08:40  qris      Americano x2                    36.000  (cost 11.400)
 *    1 Oct 15:05  transfer  Latte x1                        25.000  (cost 8.400)
 *    1 Oct 16:00  qris      Latte x3, VOIDED at 16:10       75.000  (not counted)
 *    1 Oct 20:00  waste 500 ml milk @18                      9.000
 *    1 Oct 23:50  cash      Americano x1                    18.000  (cost 5.700)
 *    2 Oct 00:10  qris      Latte x1                        25.000  (cost 8.400)
 *    2 Oct 21:00  waste 100 g coffee @250                   25.000
 *
 * 1 Oct only: revenue 147.000 from 4 sales (avg 36.750); cash 86.000 (2), qris 36.000 (1), transfer 25.000 (1);
 *             COGS 48.000, gross profit 99.000, margin 0.6734; waste 9.000.
 * 1–2 Oct:    revenue 172.000 from 5 sales; COGS 56.400; waste 34.000.
 */
function seedReportScenario(): array
{
    $owner = User::factory()->owner()->create();
    $cashier = User::factory()->cashier()->create();

    test()->travelTo(Carbon::parse('2026-09-30 06:00', 'Asia/Jakarta'));
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 10000, ['cost_per_unit' => 250]);
    $milk = stockedIngredient('Susu UHT', Unit::Ml, 20000, ['cost_per_unit' => 18]);
    $cup = stockedIngredient('Cup', Unit::Pcs, 1000, ['cost_per_unit' => 1200]);
    $latte = productWithRecipe('Latte', 25000, [[$coffee, 18], [$milk, 150], [$cup, 1]]);
    $americano = productWithRecipe('Americano', 18000, [[$coffee, 18], [$cup, 1]]);

    $at = fn (string $time) => test()->travelTo(Carbon::parse($time, 'Asia/Jakarta'));

    $at('2026-09-30 23:59');
    checkout($cashier, [[$americano, 1]], PaymentMethod::Cash, '20000');
    $at('2026-10-01 08:15');
    checkout($cashier, [[$latte, 2], [$americano, 1]], PaymentMethod::Cash, '70000');
    $at('2026-10-01 08:40');
    checkout($cashier, [[$americano, 2]], PaymentMethod::Qris);
    $at('2026-10-01 15:05');
    checkout($cashier, [[$latte, 1]], PaymentMethod::Transfer);
    $at('2026-10-01 16:00');
    $voided = checkout($cashier, [[$latte, 3]], PaymentMethod::Qris);
    $at('2026-10-01 16:10');
    app(SaleService::class)->void($voided, $owner, 'Pelanggan batal');
    $at('2026-10-01 20:00');
    app(StockService::class)->recordWaste($milk, '500', $owner, 'Susu basi');
    $at('2026-10-01 23:50');
    checkout($cashier, [[$americano, 1]], PaymentMethod::Cash, '18000');
    $at('2026-10-02 00:10');
    checkout($cashier, [[$latte, 1]], PaymentMethod::Qris);
    $at('2026-10-02 21:00');
    app(StockService::class)->recordWaste($coffee, '100', $owner, 'Biji kopi lembap');

    return compact('owner', 'cashier', 'coffee', 'milk', 'cup', 'latte', 'americano');
}
