<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->category = Category::factory()->create(['name' => 'Kopi']);
});

test('owner can list products with rupiah prices and a missing-recipe badge', function () {
    $withRecipe = Product::factory()->for($this->category)->create(['name' => 'Es Kopi Susu', 'price' => 22000]);
    Recipe::factory()->for($withRecipe)->create();
    Product::factory()->for($this->category)->create(['name' => 'Kopi Tubruk', 'price' => 15000]);

    $response = $this->actingAs($this->owner)->get(route('owner.products.index'));

    $response->assertOk()
        ->assertSee('Rp22.000')
        ->assertSee('Rp15.000')
        ->assertSeeInOrder(['Es Kopi Susu', 'Kopi Tubruk', 'Belum ada resep']);
    expect(substr_count($response->getContent(), 'Belum ada resep'))->toBe(1);
});

test('products can be filtered by category and status', function () {
    $other = Category::factory()->create(['name' => 'Teh']);
    Product::factory()->for($this->category)->create(['name' => 'Espresso']);
    Product::factory()->for($this->category)->inactive()->create(['name' => 'Kopi Lama']);
    Product::factory()->for($other)->create(['name' => 'Es Teh Manis']);

    $this->actingAs($this->owner)
        ->get(route('owner.products.index', ['category' => $this->category->id, 'status' => 'active']))
        ->assertOk()
        ->assertSee('Espresso')
        ->assertDontSee('Kopi Lama')
        ->assertDontSee('Es Teh Manis');

    $this->actingAs($this->owner)
        ->get(route('owner.products.index', ['status' => 'inactive']))
        ->assertSee('Kopi Lama')
        ->assertDontSee('Espresso');
});

test('owner can create a product with a photo', function () {
    Storage::fake('public');

    $response = $this->actingAs($this->owner)->post(route('owner.products.store'), [
        'name' => 'Cappuccino',
        'category_id' => $this->category->id,
        'price' => '25000',
        'is_active' => '1',
        'image' => UploadedFile::fake()->image('cappuccino.jpg'),
    ]);

    $product = Product::where('name', 'Cappuccino')->firstOrFail();
    $response->assertRedirect(route('owner.products.edit', $product));

    expect($product->price)->toBe('25000.00')
        ->and($product->is_active)->toBeTrue()
        ->and($product->category_id)->toBe($this->category->id);
    Storage::disk('public')->assertExists($product->image);
});

test('product validation messages are in Indonesian', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.products.store'), ['name' => '', 'category_id' => 999, 'price' => '-1'])
        ->assertSessionHasErrors([
            'name' => 'Nama wajib diisi.',
            'category_id' => 'Kategori yang dipilih tidak valid.',
            'price' => 'Harga minimal 0.',
        ]);

    expect(Product::count())->toBe(0);
});

test('owner can update a product, deactivate it and replace its photo', function () {
    Storage::fake('public');
    $oldImage = UploadedFile::fake()->image('old.jpg')->store('products', 'public');
    $product = Product::factory()->for($this->category)->create(['image' => $oldImage]);
    $newCategory = Category::factory()->create();

    $this->actingAs($this->owner)
        ->put(route('owner.products.update', $product), [
            'name' => 'Americano',
            'category_id' => $newCategory->id,
            'price' => '18000',
            // is_active unchecked -> not sent
            'image' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertRedirect(route('owner.products.index'));

    $product->refresh();
    expect($product->name)->toBe('Americano')
        ->and($product->category_id)->toBe($newCategory->id)
        ->and($product->price)->toBe('18000.00')
        ->and($product->is_active)->toBeFalse()
        ->and($product->image)->not->toBe($oldImage);
    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($product->image);
});

test('owner can open the product edit page with its recipe', function () {
    $product = Product::factory()->for($this->category)->create();
    $recipe = Recipe::factory()->for($product)->create(['quantity' => 18]);

    $this->actingAs($this->owner)
        ->get(route('owner.products.edit', $product))
        ->assertOk()
        ->assertSee($recipe->ingredient->name);
});
