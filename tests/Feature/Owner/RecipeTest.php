<?php

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create(['name' => 'Es Kopi Susu']);
    $this->coffee = Ingredient::factory()->create(['name' => 'Biji Kopi', 'unit' => Unit::Gram]);
});

test('owner can add an ingredient to a recipe', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.products.recipes.store', $this->product), [
            'ingredient_id' => $this->coffee->id,
            'quantity' => '18',
        ])
        ->assertRedirect(route('owner.products.edit', $this->product).'#resep');

    $recipe = $this->product->recipes()->sole();
    expect($recipe->ingredient_id)->toBe($this->coffee->id)
        ->and($recipe->quantity)->toBe('18.00');
});

test('recipe cannot contain the same ingredient twice', function () {
    Recipe::factory()->for($this->product)->for($this->coffee)->create(['quantity' => 18]);

    $this->actingAs($this->owner)
        ->post(route('owner.products.recipes.store', $this->product), [
            'ingredient_id' => $this->coffee->id,
            'quantity' => '20',
        ])
        ->assertSessionHasErrorsIn('recipe', ['ingredient_id' => 'Bahan ini sudah ada di resep. Ubah jumlahnya di daftar resep.']);

    expect($this->product->recipes()->count())->toBe(1)
        ->and($this->product->recipes()->first()->quantity)->toBe('18.00');
});

test('the same ingredient may be used in recipes of different products', function () {
    Recipe::factory()->for(Product::factory())->for($this->coffee)->create();

    $this->actingAs($this->owner)
        ->post(route('owner.products.recipes.store', $this->product), [
            'ingredient_id' => $this->coffee->id,
            'quantity' => '18',
        ])
        ->assertSessionHasNoErrors();

    expect($this->product->recipes()->count())->toBe(1);
});

test('database enforces the unique product-ingredient pair', function () {
    Recipe::factory()->for($this->product)->for($this->coffee)->create();

    Recipe::factory()->for($this->product)->for($this->coffee)->create();
})->throws(UniqueConstraintViolationException::class);

test('recipe quantity must be greater than zero', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.products.recipes.store', $this->product), [
            'ingredient_id' => $this->coffee->id,
            'quantity' => '0',
        ])
        ->assertSessionHasErrorsIn('recipe', ['quantity' => 'Jumlah harus lebih besar dari 0.']);

    expect($this->product->recipes()->count())->toBe(0);
});

test('owner can update a recipe quantity', function () {
    $recipe = Recipe::factory()->for($this->product)->for($this->coffee)->create(['quantity' => 18]);

    $this->actingAs($this->owner)
        ->put(route('owner.products.recipes.update', [$this->product, $recipe]), ['quantity' => '20.5'])
        ->assertRedirect(route('owner.products.edit', $this->product).'#resep');

    expect($recipe->fresh()->quantity)->toBe('20.50');
});

test('owner can remove an ingredient from a recipe', function () {
    $recipe = Recipe::factory()->for($this->product)->for($this->coffee)->create();

    $this->actingAs($this->owner)
        ->delete(route('owner.products.recipes.destroy', [$this->product, $recipe]))
        ->assertRedirect(route('owner.products.edit', $this->product).'#resep');

    expect(Recipe::find($recipe->id))->toBeNull();
});

test('a recipe row cannot be changed through another product', function () {
    $otherProduct = Product::factory()->create();
    $recipe = Recipe::factory()->for($otherProduct)->for($this->coffee)->create(['quantity' => 18]);

    $this->actingAs($this->owner)
        ->put(route('owner.products.recipes.update', [$this->product, $recipe]), ['quantity' => '99'])
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->delete(route('owner.products.recipes.destroy', [$this->product, $recipe]))
        ->assertNotFound();

    expect($recipe->fresh()->quantity)->toBe('18.00');
});

test('invalid recipe quantity update is reported under that recipe row', function () {
    $recipe = Recipe::factory()->for($this->product)->for($this->coffee)->create(['quantity' => 18]);
    $edit = route('owner.products.edit', $this->product);

    $this->actingAs($this->owner)
        ->from($edit)
        ->put(route('owner.products.recipes.update', [$this->product, $recipe]), ['quantity' => ''])
        ->assertSessionHasErrorsIn('recipe-'.$recipe->id, ['quantity' => 'Jumlah wajib diisi.']);

    expect($recipe->fresh()->quantity)->toBe('18.00');

    $html = $this->actingAs($this->owner)
        ->from($edit)
        ->followingRedirects()
        ->put(route('owner.products.recipes.update', [$this->product, $recipe]), ['quantity' => ''])
        ->assertOk()
        ->getContent();

    // The message sits inside that recipe's row.
    expect($html)->toMatch('/Biji Kopi<\/span>.*?Jumlah wajib diisi\..*?<\/li>/s');
});
