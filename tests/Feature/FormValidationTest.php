<?php

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;

/**
 * Every <form> containing a required field must opt out of browser validation, so the
 * server-side (Indonesian) messages are shown instead of the browser's English bubbles.
 */
function assertRequiredFormsUseNovalidate(string $html, string $page): void
{
    preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/s', $html, $forms, PREG_SET_ORDER);

    foreach ($forms as [$form, $attributes, $body]) {
        if (preg_match('/\srequired(\s|=|>|\/)/', $body)) {
            expect(str_contains($attributes, 'novalidate'))->toBeTrue("A form on {$page} has required fields but no novalidate.");
        }
    }
}

test('owner forms with required fields disable browser validation', function () {
    $owner = User::factory()->owner()->create();
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    Recipe::factory()->for($product)->create();
    Ingredient::factory()->create(); // so the "add ingredient" form is rendered
    $ingredient = Ingredient::factory()->create();

    $pages = [
        route('owner.categories.create'),
        route('owner.categories.edit', $category),
        route('owner.products.create'),
        route('owner.products.edit', $product),
        route('owner.ingredients.create'),
        route('owner.ingredients.edit', $ingredient),
        route('owner.stock.restock.create', $ingredient),
        route('owner.stock.adjustment.create', $ingredient),
        route('owner.stock.waste.create', $ingredient),
        route('owner.sales.show', checkout(User::factory()->cashier()->create(), [[productWithRecipe('Espresso', 15000, [[stockedIngredient('Kopi', Unit::Gram, 100), 18]]), 1]])),
    ];

    foreach ($pages as $page) {
        $html = $this->actingAs($owner)->get($page)->assertOk()->getContent();

        expect($html)->toContain('novalidate');
        assertRequiredFormsUseNovalidate($html, $page);
    }
});

test('auth forms with required fields disable browser validation', function () {
    foreach (['/login', '/forgot-password', '/reset-password/token'] as $page) {
        assertRequiredFormsUseNovalidate($this->get($page)->assertOk()->getContent(), $page);
    }

    $html = $this->actingAs(User::factory()->create())->get('/confirm-password')->assertOk()->getContent();
    assertRequiredFormsUseNovalidate($html, '/confirm-password');
});

test('empty login shows Indonesian messages under the fields', function () {
    $this->from('/login')->post('/login', ['email' => '', 'password' => ''])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Kata sandi wajib diisi.']);

    $this->from('/login')->followingRedirects()->post('/login', ['email' => '', 'password' => ''])
        ->assertOk()
        ->assertSee('Email wajib diisi.')
        ->assertSee('Kata sandi wajib diisi.');
});

test('empty category form shows the Indonesian message on the page', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->from(route('owner.categories.create'))
        ->followingRedirects()
        ->post(route('owner.categories.store'), ['name' => ''])
        ->assertOk()
        ->assertSee('Nama wajib diisi.');
});
