<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\IngredientService;
use Illuminate\Database\Seeder;

/**
 * Temporary master data so the owner pages are not empty (the full demo dataset comes in phase 5).
 * Opening stock goes through IngredientService/StockService so stock_movements stay consistent.
 */
class MasterDataSeeder extends Seeder
{
    public function run(IngredientService $ingredientService): void
    {
        $owner = User::where('role', Role::Owner)->firstOrFail();

        $categories = collect(['Kopi', 'Non-Kopi', 'Teh', 'Botolan'])
            ->mapWithKeys(fn (string $name) => [$name => Category::create(['name' => $name])]);

        // name => [unit, initial stock, min stock, cost per unit, active]
        // Enough stock for plenty of test transactions; two ingredients start below their minimum
        // (still good for dozens of portions) and one is inactive to show PLAN.md rule 8.
        $ingredients = collect([
            'Biji Kopi Arabika' => [Unit::Gram, 5000, 1000, 250, true],
            'Susu UHT' => [Unit::Ml, 20000, 5000, 18, true],
            'Gula Aren Cair' => [Unit::Ml, 900, 1000, 40, true],     // below minimum, ~45 portions
            'Gula Pasir' => [Unit::Gram, 5000, 1000, 16, true],
            'Bubuk Cokelat' => [Unit::Gram, 2000, 500, 120, true],
            'Bubuk Matcha' => [Unit::Gram, 250, 300, 450, true],     // below minimum, 50 portions
            'Teh Hitam' => [Unit::Gram, 800, 250, 150, true],
            'Es Batu' => [Unit::Gram, 40000, 5000, 1, true],
            'Cup Plastik 16 oz' => [Unit::Pcs, 500, 100, 1200, true],
            'Botol 1 L' => [Unit::Pcs, 40, 10, 4500, true],
            'Sirup Karamel' => [Unit::Ml, 1000, 200, 60, false],     // inactive
        ])->map(fn (array $row, string $name) => $ingredientService->create(
            ['name' => $name, 'unit' => $row[0], 'min_stock' => $row[2], 'cost_per_unit' => $row[3], 'is_active' => $row[4]],
            (string) $row[1],
            $owner,
        ));

        // [category, name, price, active, recipe: ingredient => quantity per portion]
        $products = [
            ['Kopi', 'Espresso', 15000, true, ['Biji Kopi Arabika' => 18, 'Cup Plastik 16 oz' => 1]],
            ['Kopi', 'Americano', 18000, true, ['Biji Kopi Arabika' => 18, 'Es Batu' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Kopi', 'Cappuccino', 25000, true, ['Biji Kopi Arabika' => 18, 'Susu UHT' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Kopi', 'Es Kopi Susu Gula Aren', 22000, true, ['Biji Kopi Arabika' => 18, 'Susu UHT' => 150, 'Gula Aren Cair' => 20, 'Es Batu' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Kopi', 'Kopi Tubruk', 15000, true, []], // intentionally without a recipe
            ['Kopi', 'Caramel Latte', 28000, true, ['Biji Kopi Arabika' => 18, 'Susu UHT' => 150, 'Sirup Karamel' => 20, 'Cup Plastik 16 oz' => 1]], // hidden in POS: inactive ingredient
            ['Non-Kopi', 'Es Cokelat', 22000, true, ['Bubuk Cokelat' => 30, 'Susu UHT' => 180, 'Gula Pasir' => 10, 'Es Batu' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Non-Kopi', 'Matcha Latte', 28000, true, ['Bubuk Matcha' => 5, 'Susu UHT' => 180, 'Gula Pasir' => 10, 'Es Batu' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Teh', 'Es Teh Manis', 15000, true, ['Teh Hitam' => 5, 'Gula Pasir' => 20, 'Es Batu' => 150, 'Cup Plastik 16 oz' => 1]],
            ['Teh', 'Teh Tarik', 18000, false, ['Teh Hitam' => 6, 'Susu UHT' => 100, 'Gula Pasir' => 15, 'Cup Plastik 16 oz' => 1]],
            ['Botolan', 'Kopi Susu Aren 1 L', 95000, true, ['Biji Kopi Arabika' => 72, 'Susu UHT' => 600, 'Gula Aren Cair' => 100, 'Botol 1 L' => 1]],
        ];

        foreach ($products as [$category, $name, $price, $active, $recipe]) {
            $product = $categories[$category]->products()->create([
                'name' => $name,
                'price' => $price,
                'is_active' => $active,
            ]);

            foreach ($recipe as $ingredientName => $quantity) {
                $product->recipes()->create([
                    'ingredient_id' => $ingredients[$ingredientName]->id,
                    'quantity' => $quantity,
                ]);
            }
        }
    }
}
