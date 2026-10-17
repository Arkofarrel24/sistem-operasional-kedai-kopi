<?php

use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
});

test('owner can list ingredients with current and minimum stock', function () {
    $ingredient = Ingredient::factory()->create(['name' => 'Susu UHT', 'unit' => Unit::Ml, 'min_stock' => 5000, 'cost_per_unit' => 18]);
    app(StockService::class)->recordInitialStock($ingredient, '12000', $this->owner);

    $this->actingAs($this->owner)
        ->get(route('owner.ingredients.index'))
        ->assertOk()
        ->assertSee('Susu UHT')
        ->assertSee('12.000 ml')
        ->assertSee('5.000 ml')
        ->assertSee('Rp18 / ml');
});

test('creating an ingredient books the opening stock as a stock movement', function () {
    $this->actingAs($this->owner)
        ->post(route('owner.ingredients.store'), [
            'name' => 'Biji Kopi Arabika',
            'unit' => 'gram',
            'initial_stock' => '2500.5',
            'min_stock' => '1000',
            'cost_per_unit' => '250',
        ])
        ->assertRedirect(route('owner.ingredients.index'));

    $ingredient = Ingredient::where('name', 'Biji Kopi Arabika')->firstOrFail();
    expect($ingredient->unit)->toBe(Unit::Gram)
        ->and($ingredient->current_stock)->toBe('2500.50')
        ->and($ingredient->min_stock)->toBe('1000.00')
        ->and($ingredient->cost_per_unit)->toBe('250.00');

    $movement = $ingredient->stockMovements()->sole();
    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe('2500.50')
        ->and($movement->note)->toBe('Stok awal')
        ->and($movement->user_id)->toBe($this->owner->id);
});

test('zero opening stock creates no movement', function () {
    $this->actingAs($this->owner)->post(route('owner.ingredients.store'), [
        'name' => 'Cup Plastik', 'unit' => 'pcs', 'initial_stock' => '0', 'min_stock' => '100', 'cost_per_unit' => '1200',
    ]);

    $ingredient = Ingredient::where('name', 'Cup Plastik')->firstOrFail();
    expect($ingredient->current_stock)->toBe('0.00')
        ->and($ingredient->stockMovements()->count())->toBe(0);
});

test('ingredient validation rejects invalid input with Indonesian messages', function () {
    Ingredient::factory()->create(['name' => 'Gula Pasir']);

    $this->actingAs($this->owner)
        ->post(route('owner.ingredients.store'), [
            'name' => 'Gula Pasir',
            'unit' => 'liter',
            'initial_stock' => '-5',
            'min_stock' => '',
            'cost_per_unit' => 'abc',
        ])
        ->assertSessionHasErrors([
            'name' => 'Nama sudah digunakan.',
            'unit' => 'Satuan yang dipilih tidak valid.',
            'initial_stock' => 'Stok awal minimal 0.',
            'min_stock' => 'Stok minimum wajib diisi.',
            'cost_per_unit',
        ]);

    expect(Ingredient::count())->toBe(1);
});

test('owner can update an ingredient but not its unit or stock', function () {
    $ingredient = Ingredient::factory()->create(['name' => 'Susu', 'unit' => Unit::Ml]);
    app(StockService::class)->recordInitialStock($ingredient, '1000', $this->owner);

    $this->actingAs($this->owner)
        ->put(route('owner.ingredients.update', $ingredient), [
            'name' => 'Susu UHT',
            'unit' => 'gram',
            'current_stock' => '999999',
            'min_stock' => '2000',
            'cost_per_unit' => '19.5',
            'is_active' => '1',
        ])
        ->assertRedirect(route('owner.ingredients.index'));

    $ingredient->refresh();
    expect($ingredient->name)->toBe('Susu UHT')
        ->and($ingredient->unit)->toBe(Unit::Ml)
        ->and($ingredient->current_stock)->toBe('1000.00')
        ->and($ingredient->min_stock)->toBe('2000.00')
        ->and($ingredient->cost_per_unit)->toBe('19.50');
});

test('owner can open the ingredient forms', function () {
    $ingredient = Ingredient::factory()->create();

    $this->actingAs($this->owner)->get(route('owner.ingredients.create'))->assertOk();
    $this->actingAs($this->owner)->get(route('owner.ingredients.edit', $ingredient))->assertOk();
});

test('owner can deactivate and reactivate an ingredient', function () {
    $ingredient = Ingredient::factory()->create(['name' => 'Sirup Karamel', 'unit' => Unit::Ml]);
    $data = ['name' => 'Sirup Karamel', 'min_stock' => '200', 'cost_per_unit' => '60'];

    // Unchecked checkbox: the field is not sent at all.
    $this->actingAs($this->owner)->put(route('owner.ingredients.update', $ingredient), $data)->assertSessionHasNoErrors();
    expect($ingredient->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->owner)->get(route('owner.ingredients.index'))->assertSeeInOrder(['Sirup Karamel', 'Nonaktif']);

    $this->actingAs($this->owner)->put(route('owner.ingredients.update', $ingredient), $data + ['is_active' => '1']);
    expect($ingredient->refresh()->is_active)->toBeTrue();
});

test('new ingredients are active unless unchecked', function () {
    $base = ['unit' => 'ml', 'initial_stock' => '0', 'min_stock' => '0', 'cost_per_unit' => '1'];

    $this->actingAs($this->owner)->post(route('owner.ingredients.store'), $base + ['name' => 'Aktif', 'is_active' => '1']);
    $this->actingAs($this->owner)->post(route('owner.ingredients.store'), $base + ['name' => 'Tidak Aktif']);

    expect(Ingredient::where('name', 'Aktif')->value('is_active'))->toBeTruthy()
        ->and(Ingredient::where('name', 'Tidak Aktif')->value('is_active'))->toBeFalsy();
});
