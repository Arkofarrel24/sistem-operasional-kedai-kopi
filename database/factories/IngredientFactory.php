<?php

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stock starts at 0; use StockService to give an ingredient stock so movements stay consistent.
 *
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(Unit::cases()),
            'min_stock' => fake()->numberBetween(1, 20) * 100,
            'cost_per_unit' => fake()->numberBetween(1, 300),
        ];
    }
}
