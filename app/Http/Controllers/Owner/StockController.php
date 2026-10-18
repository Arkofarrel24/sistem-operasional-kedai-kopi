<?php

namespace App\Http\Controllers\Owner;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(): View
    {
        $ingredients = Ingredient::orderBy('name')->get();

        return view('owner.stock.index', [
            'ingredients' => $ingredients,
            'totalValue' => $ingredients->reduce(fn (string $sum, Ingredient $ingredient) => bcadd($sum, $ingredient->stockValue(), 2), '0'),
        ]);
    }

    /**
     * Movement history of one ingredient, filterable by type and date range (Asia/Jakarta days).
     */
    public function show(Request $request, Ingredient $ingredient): View
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $movements = $ingredient->stockMovements()
            ->with(['user', 'sale'])
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('owner.stock.show', [
            'ingredient' => $ingredient,
            'movements' => $movements,
            'filters' => $filters,
            'types' => StockMovementType::cases(),
        ]);
    }
}
