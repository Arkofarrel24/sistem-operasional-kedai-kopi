<?php

use App\Enums\Unit;
use App\Models\Sale;
use App\Models\User;

/**
 * Every stock, sales and void route as [method, route name], with an ingredient and a sale to address.
 */
dataset('owner stock and sales routes', [
    ['get', 'owner.stock.index'],
    ['get', 'owner.stock.show'],
    ['get', 'owner.stock.restock.create'],
    ['post', 'owner.stock.restock.store'],
    ['get', 'owner.stock.adjustment.create'],
    ['post', 'owner.stock.adjustment.store'],
    ['get', 'owner.stock.waste.create'],
    ['post', 'owner.stock.waste.store'],
    ['get', 'owner.sales.index'],
    ['get', 'owner.sales.show'],
    ['post', 'owner.sales.void'],
]);

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $this->sale = checkout($this->cashier, [[productWithRecipe('Espresso', 15000, [[$this->coffee, 18]]), 1]]);
});

function stockRouteUrl(string $name, $ingredient, Sale $sale): string
{
    return match (true) {
        str_starts_with($name, 'owner.stock.') && $name !== 'owner.stock.index' => route($name, $ingredient),
        in_array($name, ['owner.sales.show', 'owner.sales.void'], true) => route($name, $sale),
        default => route($name),
    };
}

test('cashier gets 403 on stock and void routes', function (string $method, string $name) {
    $this->actingAs($this->cashier)
        ->{$method}(stockRouteUrl($name, $this->coffee, $this->sale), ['quantity' => '10', 'total_cost' => '1', 'counted_stock' => '0', 'reason' => 'x'])
        ->assertForbidden();

    expect($this->coffee->refresh()->current_stock)->toBe('982.00')
        ->and($this->sale->refresh()->isVoid())->toBeFalse();
})->with('owner stock and sales routes');

test('guest is redirected to login on stock and void routes', function (string $method, string $name) {
    $this->{$method}(stockRouteUrl($name, $this->coffee, $this->sale))->assertRedirect('/login');
})->with('owner stock and sales routes');
