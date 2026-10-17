<?php

use App\Enums\Unit;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 10:00', 'Asia/Jakarta'));

    $this->cashier = User::factory()->cashier()->create(['name' => 'Ayu Lestari']);
    $this->otherCashier = User::factory()->cashier()->create(['name' => 'Budi Santoso']);
    $coffee = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $this->americano = productWithRecipe('Americano', 18000, [[$coffee, 18]]);
});

test('owner gets 403 on every POS page', function () {
    $owner = User::factory()->owner()->create();
    $sale = checkout($this->cashier, [[$this->americano, 1]]);

    foreach (['/pos', '/pos/history', "/pos/sales/{$sale->id}"] as $url) {
        $this->actingAs($owner)->get($url)->assertForbidden();
    }
});

test('guest is redirected to login from POS pages', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/pos', '/pos/history', '/pos/sales/1']);

test('cashier history shows only their own sales of today', function () {
    $this->travelTo(Carbon::parse('2026-10-16 21:00', 'Asia/Jakarta'));
    $yesterday = checkout($this->cashier, [[$this->americano, 1]]);

    $this->travelTo(Carbon::parse('2026-10-17 10:00', 'Asia/Jakarta'));
    $mine = checkout($this->cashier, [[$this->americano, 2]]);
    $theirs = checkout($this->otherCashier, [[$this->americano, 1]]);

    $this->actingAs($this->cashier)
        ->get('/pos/history')
        ->assertOk()
        ->assertSee([$mine->invoice_no, 'Rp36.000', '10:00', '17 Okt 2026'])
        ->assertDontSee($theirs->invoice_no)
        ->assertDontSee($yesterday->invoice_no);
});

test('history shows an empty state', function () {
    $this->actingAs($this->cashier)->get('/pos/history')->assertOk()->assertSee('Belum ada transaksi hari ini.');
});

test('cashier can open their own receipt but not another cashier\'s', function () {
    $mine = checkout($this->cashier, [[$this->americano, 1]]);
    $theirs = checkout($this->otherCashier, [[$this->americano, 1]]);

    $this->actingAs($this->cashier)
        ->get("/pos/sales/{$mine->id}")
        ->assertOk()
        ->assertSee([$mine->invoice_no, 'Americano', 'Rp18.000', 'Ayu Lestari', 'Cetak struk']);

    $this->actingAs($this->cashier)->get("/pos/sales/{$theirs->id}")->assertForbidden();
});

test('a wrong role gets 403 even for ids that do not exist', function () {
    $this->actingAs(User::factory()->owner()->create())->get('/pos/sales/999999')->assertForbidden();
    $this->actingAs($this->cashier)->get('/owner/products/999999/edit')->assertForbidden();
});
