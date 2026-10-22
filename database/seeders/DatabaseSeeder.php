<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Accounts and master data (with their opening stock) are dated at the start of the demo period,
     * so the 30 days of history that follow read in order.
     */
    public function run(): void
    {
        $now = now();

        Carbon::setTestNow($now->copy()->startOfDay()->subDays(DemoTransactionSeeder::DEFAULT_DAYS)->setTime(6, 0));

        try {
            $this->call([
                UserSeeder::class,
                MasterDataSeeder::class,
            ]);

            $this->call(DemoTransactionSeeder::class, parameters: ['until' => $now]);
        } finally {
            Carbon::setTestNow();
        }
    }
}
