<?php

use App\Enums\Unit;
use App\Livewire\Pos\Register;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->cashier = User::factory()->cashier()->create();
    $this->coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $this->milk = stockedIngredient('Susu UHT', Unit::Ml, 300);

    $this->kopi = Category::factory()->create(['name' => 'Kopi']);
    $this->teh = Category::factory()->create(['name' => 'Teh']);

    $this->esKopiSusu = productWithRecipe('Es Kopi Susu', 22000, [[$this->coffee, 18], [$this->milk, 150]], ['category_id' => $this->kopi->id]);
    $this->americano = productWithRecipe('Americano', 18000, [[$this->coffee, 18]], ['category_id' => $this->kopi->id]);
    $this->esTeh = productWithRecipe('Es Teh Manis', 15000, [[$this->coffee, 1]], ['category_id' => $this->teh->id]);

    $this->register = fn () => Livewire::actingAs($this->cashier)->test(Register::class);
});

test('the grid lists only sellable products', function () {
    Product::factory()->create(['name' => 'Kopi Tubruk', 'category_id' => $this->kopi->id]);
    productWithRecipe('Teh Tarik', 18000, [[$this->milk, 100]], ['category_id' => $this->teh->id, 'is_active' => false]);
    $caramel = stockedIngredient('Sirup Karamel', Unit::Ml, 500, ['is_active' => false]);
    productWithRecipe('Caramel Latte', 28000, [[$this->coffee, 18], [$caramel, 20]], ['category_id' => $this->kopi->id]);

    ($this->register)()
        ->assertSee(['Es Kopi Susu', 'Americano', 'Es Teh Manis', 'Rp22.000'])
        ->assertDontSee('Kopi Tubruk')
        ->assertDontSee('Teh Tarik')
        ->assertDontSee('Caramel Latte');
});

test('search and category filter the grid', function () {
    ($this->register)()
        ->set('search', 'ameri')
        ->assertSee('Americano')
        ->assertDontSee('Es Kopi Susu')
        ->set('search', '')
        ->call('selectCategory', $this->teh->id)
        ->assertSee('Es Teh Manis')
        ->assertDontSee('Americano')
        ->call('resetFilters')
        ->assertSee(['Americano', 'Es Teh Manis']);
});

test('cart quantities and total update with add, plus and minus', function () {
    $id = $this->esKopiSusu->id;

    ($this->register)()
        ->call('add', $id)
        ->call('add', $id)
        ->assertSet("cart.{$id}.quantity", 2)
        ->assertSee('Bayar Rp44.000')
        ->call('add', $this->americano->id)
        ->assertSee('Bayar Rp62.000')
        ->call('decrement', $id)
        ->assertSet("cart.{$id}.quantity", 1)
        ->call('increment', $id)
        ->assertSet("cart.{$id}.quantity", 2)
        ->call('remove', $this->americano->id)
        ->call('decrement', $id)
        ->call('decrement', $id)
        ->assertSet('cart', []);
});

test('products that cannot be sold are not added to the cart', function () {
    $noRecipe = Product::factory()->create(['name' => 'Kopi Tubruk']);

    ($this->register)()->call('add', $noRecipe->id)->assertSet('cart', []);
});

test('cash checkout shows the receipt with change and empties the cart', function () {
    $component = ($this->register)()
        ->call('add', $this->esKopiSusu->id)
        ->set("cart.{$this->esKopiSusu->id}.note", 'less sugar')
        ->set('paidAmount', '50000')
        ->assertSee('Rp28.000')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = Sale::sole();
    $component->assertSet('cart', [])
        ->assertSet('paidAmount', '')
        ->assertSet('completedSaleId', $sale->id)
        ->assertSee(['Pembayaran berhasil', $sale->invoice_no, 'Kembalian', 'Rp28.000', 'Catatan: less sugar']);

    expect($sale->items()->sole()->note)->toBe('less sugar')
        ->and($sale->change_amount)->toBe('28000.00');

    $component->call('newTransaction')->assertSet('completedSaleId', null)->assertDontSee('Pembayaran berhasil');
});

test('quick cash buttons offer the exact amount and round banknotes above the total', function () {
    ($this->register)()
        ->call('add', $this->esKopiSusu->id)
        ->call('add', $this->americano->id) // total Rp40.000
        ->assertSet('quickAmounts', ['40000', '50000', '100000'])
        ->call('setPaidAmount', '40000')
        ->call('checkout')
        ->assertHasNoErrors();

    expect(Sale::sole()->change_amount)->toBe('0.00');
});

test('cash below the total or missing is rejected with Indonesian messages', function () {
    ($this->register)()
        ->call('add', $this->esKopiSusu->id)
        ->call('checkout')
        ->assertHasErrors(['paidAmount' => 'Masukkan jumlah uang yang diterima.'])
        ->set('paidAmount', '20000')
        ->assertSee('Kurang')
        ->call('checkout')
        ->assertHasErrors(['paidAmount' => 'Uang diterima kurang dari total Rp22.000.'])
        ->assertSet("cart.{$this->esKopiSusu->id}.quantity", 1);

    expect(Sale::count())->toBe(0);
});

test('qris and transfer need no cash amount', function (string $method) {
    ($this->register)()
        ->call('add', $this->americano->id)
        ->call('selectPayment', $method)
        ->call('checkout')
        ->assertHasNoErrors();

    expect(Sale::sole()->payment_method->value)->toBe($method);
})->with(['qris', 'transfer']);

test('an empty cart cannot be checked out', function () {
    ($this->register)()
        ->call('selectPayment', 'qris')
        ->call('checkout')
        ->assertHasErrors(['cart' => 'Keranjang masih kosong.']);
});

test('insufficient stock is shown and the cart is kept', function () {
    $id = $this->esKopiSusu->id;

    ($this->register)()
        ->call('add', $id)
        ->call('add', $id)
        ->call('add', $id) // needs 450 ml milk, 300 ml in stock
        ->call('selectPayment', 'qris')
        ->call('checkout')
        ->assertHasErrors('checkout')
        ->assertSee('Stok tidak cukup: Susu UHT kurang 150 ml.')
        ->assertSet("cart.{$id}.quantity", 3)
        ->assertSet('completedSaleId', null);

    expect(Sale::count())->toBe(0);
});

test('a tampered cart cannot sell a hidden product', function () {
    $noRecipe = Product::factory()->create(['name' => 'Kopi Tubruk']);

    ($this->register)()
        ->set('cart', [$noRecipe->id => ['quantity' => 1, 'note' => '']])
        ->call('selectPayment', 'qris')
        ->call('checkout')
        ->assertHasErrors('checkout')
        ->assertSee('Menu Kopi Tubruk tidak tersedia untuk dijual.');

    expect(Sale::count())->toBe(0);
});

test('the pay form disables browser validation', function () {
    $this->actingAs($this->cashier)
        ->get('/pos')
        ->assertOk()
        ->assertSeeLivewire(Register::class)
        ->assertSee('<form wire:submit="checkout" novalidate', escape: false);
});
