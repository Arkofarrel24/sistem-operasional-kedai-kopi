<?php

use App\Enums\Unit;
use App\Models\Category;
use App\Models\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Routes that need no login or are not part of the owner/cashier areas.
 */
const PUBLIC_ROUTES = [
    'login', 'logout', 'dashboard', 'password.request', 'password.email', 'password.reset', 'password.store',
    'password.confirm', 'verification.notice', 'verification.verify', 'verification.send', 'storage.local', 'storage.local.upload',
];

/**
 * Unnamed public routes (Breeze posts back to the same URI as its named GET route).
 */
const PUBLIC_URIS = ['login', 'confirm-password'];

function appRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route) => str_contains($route->uri(), 'livewire') || str_starts_with($route->uri(), '_') || in_array($route->uri(), ['up', '/'], true))
        ->values()
        ->all();
}

beforeEach(function () {
    $this->owner = User::factory()->owner()->create();
    $this->cashier = User::factory()->cashier()->create();

    $ingredient = stockedIngredient('Biji Kopi', Unit::Gram, 1000);
    $product = productWithRecipe('Espresso', 15000, [[$ingredient, 18]], ['category_id' => Category::factory()->create()->id]);

    // One existing record for every route parameter name.
    $this->parameters = [
        'category' => $product->category_id,
        'product' => $product->id,
        'recipe' => $product->recipes()->first()->id,
        'ingredient' => $ingredient->id,
        'sale' => checkout($this->cashier, [[$product, 1]])->id,
        'user' => $this->cashier->id,
    ];
});

function requestRoute($test, RouteDefinition $route): TestResponse
{
    $method = strtolower(collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first());
    $parameters = collect($route->parameterNames())->mapWithKeys(fn ($name) => [$name => $test->parameters[$name]])->all();

    return $test->{$method}(route($route->getName(), $parameters));
}

test('every route is either public, owner-only or cashier-only', function () {
    $unclassified = collect(appRoutes())
        ->reject(fn (RouteDefinition $route) => in_array($route->getName(), PUBLIC_ROUTES, true)
            || ($route->getName() === null && in_array($route->uri(), PUBLIC_URIS, true))
            || str_starts_with((string) $route->getName(), 'owner.')
            || str_starts_with((string) $route->getName(), 'pos.'))
        ->map(fn (RouteDefinition $route) => implode('|', $route->methods()).' '.$route->uri().' ('.($route->getName() ?? 'no name').')');

    expect($unclassified->all())->toBe([], 'Classify new routes as owner., pos. or public in RouteProtectionTest.');
});

test('owner routes are protected by auth and role:owner, cashier routes by auth and role:cashier', function () {
    foreach (appRoutes() as $route) {
        $middleware = $route->gatherMiddleware();
        $name = (string) $route->getName();

        if (str_starts_with($name, 'owner.')) {
            expect(in_array('auth', $middleware, true))->toBeTrue("{$name} must require login")
                ->and(in_array('role:owner', $middleware, true))->toBeTrue("{$name} must be owner-only");
        }

        if (str_starts_with($name, 'pos.')) {
            expect(in_array('auth', $middleware, true))->toBeTrue("{$name} must require login")
                ->and(in_array('role:cashier', $middleware, true))->toBeTrue("{$name} must be cashier-only");
        }
    }
});

test('cashiers get 403 and guests are sent to login on every owner route', function () {
    $routes = collect(appRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'owner.'));
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $this->actingAs($this->cashier);
        expect(requestRoute($this, $route)->status())->toBe(403, "cashier on {$route->getName()}");

        auth()->logout();
        $response = requestRoute($this, $route);
        expect($response->status())->toBe(302, "guest on {$route->getName()}")
            ->and($response->headers->get('Location'))->toBe(route('login'));
    }
});

test('owners get 403 and guests are sent to login on every cashier route', function () {
    $routes = collect(appRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'pos.'));
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $this->actingAs($this->owner);
        expect(requestRoute($this, $route)->status())->toBe(403, "owner on {$route->getName()}");

        auth()->logout();
        $response = requestRoute($this, $route);
        expect($response->status())->toBe(302, "guest on {$route->getName()}")
            ->and($response->headers->get('Location'))->toBe(route('login'));
    }
});
