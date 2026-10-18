<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * stock_movements is the source of truth; ingredients.current_stock is a cache that must equal
 * SUM(quantity) per ingredient (PLAN.md section 5).
 */
#[Signature('stock:verify')]
#[Description('Bandingkan stok tiap bahan dengan total pergerakan stoknya')]
class VerifyStock extends Command
{
    public function handle(): int
    {
        $ingredients = Ingredient::query()
            ->withSum('stockMovements as movements_total', 'quantity')
            ->orderBy('name')
            ->get();

        $mismatches = $ingredients
            ->map(function (Ingredient $ingredient) {
                $movements = bcadd((string) ($ingredient->movements_total ?? '0'), '0', 2);

                return [
                    'name' => $ingredient->name,
                    'stock' => $ingredient->current_stock,
                    'movements' => $movements,
                    'difference' => bcsub($ingredient->current_stock, $movements, 2),
                ];
            })
            ->filter(fn (array $row) => bccomp($row['difference'], '0', 2) !== 0)
            ->values();

        if ($mismatches->isEmpty()) {
            $this->info("Semua stok konsisten ({$ingredients->count()} bahan).");

            return self::SUCCESS;
        }

        $this->error("Ditemukan selisih pada {$mismatches->count()} bahan:");
        $this->table(['Bahan', 'current_stock', 'Total movement', 'Selisih'], $mismatches->map(fn (array $row) => array_values($row))->all());

        return self::FAILURE;
    }
}
