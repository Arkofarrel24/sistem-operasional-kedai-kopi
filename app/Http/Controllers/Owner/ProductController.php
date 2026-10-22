<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ProductRequest;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $products = Product::query()
            ->with('category')
            ->withCount([
                'recipes',
                'recipes as inactive_ingredients_count' => fn ($query) => $query->whereRelation('ingredient', 'is_active', false),
            ])
            ->when($filters['category'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('owner.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'filters' => $filters,
            // Menus that never reach the register until they get a recipe (PLAN.md rule 6).
            'withoutRecipe' => Product::where('is_active', true)->doesntHave('recipes')->count(),
        ]);
    }

    public function create(): View
    {
        return view('owner.products.create', [
            'product' => new Product(['is_active' => true]),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        return to_route('owner.products.edit', $product)
            ->with('status', 'Menu ditambahkan. Lengkapi resepnya agar menu bisa dijual di kasir.');
    }

    public function edit(Product $product): View
    {
        $product->load(['recipes' => fn ($query) => $query->with('ingredient')->orderBy('id')]);

        return view('owner.products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'availableIngredients' => Ingredient::whereNotIn('id', $product->recipes->pluck('ingredient_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if ($request->hasFile('image')) {
            $oldImage = $product->image;
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        if (isset($oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return to_route('owner.products.index')->with('status', 'Menu diperbarui.');
    }
}
