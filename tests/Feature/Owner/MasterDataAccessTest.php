<?php

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;

/**
 * Every master data route, as [method, route name, parameters].
 */
function masterDataRoutes(): array
{
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $ingredient = Ingredient::factory()->create();
    $recipe = Recipe::factory()->for($product)->for($ingredient)->create();

    return [
        ['get', 'owner.categories.index', []],
        ['get', 'owner.categories.create', []],
        ['post', 'owner.categories.store', []],
        ['get', 'owner.categories.edit', [$category]],
        ['put', 'owner.categories.update', [$category]],
        ['delete', 'owner.categories.destroy', [$category]],
        ['get', 'owner.products.index', []],
        ['get', 'owner.products.create', []],
        ['post', 'owner.products.store', []],
        ['get', 'owner.products.edit', [$product]],
        ['put', 'owner.products.update', [$product]],
        ['post', 'owner.products.recipes.store', [$product]],
        ['put', 'owner.products.recipes.update', [$product, $recipe]],
        ['delete', 'owner.products.recipes.destroy', [$product, $recipe]],
        ['get', 'owner.ingredients.index', []],
        ['get', 'owner.ingredients.create', []],
        ['post', 'owner.ingredients.store', []],
        ['get', 'owner.ingredients.edit', [$ingredient]],
        ['put', 'owner.ingredients.update', [$ingredient]],
    ];
}

test('cashier gets 403 on every master data route', function () {
    $cashier = User::factory()->cashier()->create();

    foreach (masterDataRoutes() as [$method, $name, $parameters]) {
        $this->actingAs($cashier)
            ->{$method}(route($name, $parameters))
            ->assertForbidden();
    }

    // Nothing was changed or removed.
    expect(Category::count())->toBe(1)
        ->and(Product::count())->toBe(1)
        ->and(Ingredient::count())->toBe(1)
        ->and(Recipe::count())->toBe(1);
});

test('guest is redirected to login on every master data route', function () {
    foreach (masterDataRoutes() as [$method, $name, $parameters]) {
        $this->{$method}(route($name, $parameters))->assertRedirect('/login');
    }
});
