<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can list categories', function () {
    Category::factory()->create(['name' => 'Kopi']);

    $this->actingAs($this->owner)
        ->get(route('owner.categories.index'))
        ->assertOk()
        ->assertSee('Kopi');
});

test('owner can create a category', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.categories.store'), ['name' => 'Non-Kopi'])
        ->assertRedirect(route('owner.categories.index'));

    expect(Category::where('name', 'Non-Kopi')->exists())->toBeTrue();
});

test('category name is required and unique', function () {
    Category::factory()->create(['name' => 'Kopi']);

    $this->actingAs($this->owner)
        ->post(route('owner.categories.store'), ['name' => ''])
        ->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);

    $this->actingAs($this->owner)
        ->post(route('owner.categories.store'), ['name' => 'Kopi'])
        ->assertSessionHasErrors(['name' => 'Nama sudah digunakan.']);

    expect(Category::count())->toBe(1);
});

test('owner can update a category and keep its own name', function () {
    $category = Category::factory()->create(['name' => 'Kopi']);

    $this->actingAs($this->owner)
        ->put(route('owner.categories.update', $category), ['name' => 'Kopi'])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->owner)
        ->put(route('owner.categories.update', $category), ['name' => 'Kopi Susu'])
        ->assertRedirect(route('owner.categories.index'));

    expect($category->fresh()->name)->toBe('Kopi Susu');
});

test('owner can delete an empty category', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('owner.categories.destroy', $category))
        ->assertRedirect(route('owner.categories.index'));

    expect(Category::find($category->id))->toBeNull();
});

test('category that still has products cannot be deleted', function () {
    $category = Category::factory()->create(['name' => 'Kopi']);
    Product::factory()->for($category)->create();

    $this->actingAs($this->owner)
        ->delete(route('owner.categories.destroy', $category))
        ->assertRedirect(route('owner.categories.index'))
        ->assertSessionHas('error');

    expect(Category::find($category->id))->not->toBeNull();
});
