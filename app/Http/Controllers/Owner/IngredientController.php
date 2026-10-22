<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\IngredientRequest;
use App\Models\Ingredient;
use App\Services\IngredientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::withCount('products')->orderBy('name')->paginate(20);

        return view('owner.ingredients.index', compact('ingredients'));
    }

    public function create(): View
    {
        return view('owner.ingredients.create', [
            'ingredient' => new Ingredient,
            'units' => Unit::cases(),
        ]);
    }

    public function store(IngredientRequest $request, IngredientService $ingredients): RedirectResponse
    {
        $ingredients->create(
            $request->safe()->except('initial_stock'),
            $request->validated('initial_stock'),
            $request->user(),
        );

        return to_route('owner.ingredients.index')->with('status', 'Bahan ditambahkan.');
    }

    public function edit(Ingredient $ingredient): View
    {
        return view('owner.ingredients.edit', compact('ingredient'));
    }

    public function update(IngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $ingredient->update($request->validated());

        return to_route('owner.ingredients.index')->with('status', 'Bahan diperbarui.');
    }
}
