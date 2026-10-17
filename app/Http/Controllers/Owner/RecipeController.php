<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreRecipeRequest;
use App\Http\Requests\Owner\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;

class RecipeController extends Controller
{
    public function store(StoreRecipeRequest $request, Product $product): RedirectResponse
    {
        $product->recipes()->create($request->validated());

        return $this->backToProduct($product, 'Bahan ditambahkan ke resep.');
    }

    public function update(UpdateRecipeRequest $request, Product $product, Recipe $recipe): RedirectResponse
    {
        $recipe->update($request->validated());

        return $this->backToProduct($product, 'Jumlah bahan diperbarui.');
    }

    public function destroy(Product $product, Recipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return $this->backToProduct($product, 'Bahan dihapus dari resep.');
    }

    private function backToProduct(Product $product, string $message): RedirectResponse
    {
        return to_route('owner.products.edit', $product)->withFragment('resep')->with('status', $message);
    }
}
