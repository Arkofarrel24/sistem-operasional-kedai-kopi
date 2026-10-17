<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('product photo url follows the host being browsed, not APP_URL', function (string $host) {
    Storage::fake('public');
    config(['app.url' => 'http://sistem-operasional-kedai-kopi.test']);

    $owner = User::factory()->owner()->create();
    $path = UploadedFile::fake()->image('kopi.jpg')->store('products', 'public');
    Product::factory()->create(['image' => $path]);

    $this->actingAs($owner)
        ->get($host.'/owner/products')
        ->assertOk()
        ->assertSee('src="'.$host.'/storage/'.$path.'"', escape: false);
})->with([
    'artisan serve' => 'http://127.0.0.1:8001',
    'herd' => 'http://sistem-operasional-kedai-kopi.test',
]);
