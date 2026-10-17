<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IngredientService
{
    public function __construct(private StockService $stock) {}

    /**
     * Create an ingredient and book its opening stock as a stock movement.
     *
     * @param  array{name: string, unit: string, min_stock: string, cost_per_unit: string}  $attributes
     */
    public function create(array $attributes, string $initialStock, User $user): Ingredient
    {
        return DB::transaction(function () use ($attributes, $initialStock, $user) {
            $ingredient = Ingredient::create($attributes);

            $this->stock->recordInitialStock($ingredient, $initialStock, $user);

            return $ingredient;
        });
    }
}
