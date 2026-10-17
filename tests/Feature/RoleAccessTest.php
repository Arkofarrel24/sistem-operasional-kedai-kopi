<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('owner is redirected to the owner dashboard after login', function () {
    $owner = User::factory()->owner()->create();

    $response = $this->post('/login', [
        'email' => $owner->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($owner);
    $response->assertRedirect('/owner/dashboard');
});

test('cashier is redirected to the pos after login', function () {
    $cashier = User::factory()->cashier()->create();

    $response = $this->post('/login', [
        'email' => $cashier->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($cashier);
    $response->assertRedirect('/pos');
});

test('cashier gets 403 on the owner dashboard', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get('/owner/dashboard')->assertForbidden();
});

test('owner gets 403 on the pos', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/pos')->assertForbidden();
});

test('guest is redirected to login from protected pages', function (string $url) {
    $this->get($url)->assertRedirect('/login');
})->with(['/owner/dashboard', '/pos']);

test('each role can open its own pages', function () {
    $owner = User::factory()->owner()->create();
    $cashier = User::factory()->cashier()->create();

    foreach (['/owner/dashboard', '/owner/products', '/owner/ingredients', '/owner/stock', '/owner/reports', '/owner/users'] as $url) {
        $this->actingAs($owner)->get($url)->assertOk();
    }

    $this->actingAs($cashier)->get('/pos')->assertOk()->assertSee($cashier->name);
});

test('logged-in user opening the login page is sent to their home', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->get('/login')->assertRedirect('/owner/dashboard');
});

test('role column defaults to cashier', function () {
    DB::table('users')->insert([
        'name' => 'Tanpa Peran',
        'email' => 'no-role@demo.test',
        'password' => bcrypt('password'),
    ]);

    expect(User::where('email', 'no-role@demo.test')->first()->role)->toBe(Role::Cashier);
});
