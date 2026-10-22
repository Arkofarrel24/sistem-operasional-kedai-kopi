<?php

use App\Enums\StockMovementType;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoTransactionSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\UserSeeder;

test('demo seeder records history through the services and keeps stock consistent', function () {
    $until = Carbon::parse('2026-10-18 17:00', 'Asia/Jakarta');

    $this->travelTo($until->copy()->startOfDay()->subDays(3)->setTime(6, 0));
    $this->seed([UserSeeder::class, MasterDataSeeder::class]);
    app(DemoTransactionSeeder::class)->setContainer(app())->__invoke(['until' => $until, 'days' => 3]);

    expect(Sale::count())->toBeGreaterThan(60)
        ->and(Sale::where('created_at', '>', $until)->exists())->toBeFalse()
        ->and(Sale::whereDate('created_at', '2026-10-15')->min('invoice_no'))->toBe('INV-20261015-0001')
        ->and(StockMovement::where('type', StockMovementType::Sale)->whereNull('unit_cost')->exists())->toBeFalse()
        ->and(StockMovement::where('type', StockMovementType::Sale)->distinct('sale_id')->count('sale_id'))->toBe(Sale::count());

    $this->artisan('stock:verify')->expectsOutputToContain('Semua stok konsisten')->assertExitCode(0);
});

test('demo history always ends today and only uses active cashiers', function () {
    $this->travelTo(Carbon::parse('2026-10-22 15:00', 'Asia/Jakarta'));
    $now = now();

    $this->travelTo($now->copy()->startOfDay()->subDays(2)->setTime(6, 0));
    $this->seed([UserSeeder::class, MasterDataSeeder::class]);
    app(DemoTransactionSeeder::class)->setContainer(app())->__invoke(['until' => $now, 'days' => 2]);

    $inactive = User::where('email', 'kasir3@demo.test')->sole();
    expect($inactive->is_active)->toBeFalse()
        ->and(Sale::max('created_at'))->toStartWith('2026-10-22')
        ->and(Sale::min('created_at'))->toStartWith('2026-10-20')
        ->and(Sale::where('created_at', '>', $now)->exists())->toBeFalse()
        ->and(Sale::where('user_id', $inactive->id)->exists())->toBeFalse();
});
