<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreRecipeRequest;
use App\Http\Requests\Owner\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    public function store(StoreRecipeRequest $request, Product $product): RedirectResponse
    {
        $product->recipes()->create($request->validated());

        return $this->backToProduct($product, 'Bahan ditambahkan ke resep.');
    }

    /**
     * Save every quantity of the recipe at once, so editing several rows never loses one.
     * Ids that are not this product's recipe rows are ignored.
     */
    public function updateAll(UpdateRecipeRequest $request, Product $product): RedirectResponse
    {
        $quantities = $request->validated('quantities');

        DB::transaction(function () use ($product, $quantities) {
            foreach ($product->recipes as $recipe) {
                if (isset($quantities[$recipe->id])) {
                    $recipe->update(['quantity' => $quantities[$recipe->id]]);
                }
            }
        });

        return $this->backToProduct($product, 'Resep disimpan.');
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
