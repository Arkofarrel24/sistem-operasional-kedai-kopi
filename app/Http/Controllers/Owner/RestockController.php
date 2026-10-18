<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\RestockRequest;
use App\Models\Ingredient;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RestockController extends Controller
{
    public function create(Ingredient $ingredient): View
    {
        return view('owner.stock.restock', compact('ingredient'));
    }

    public function store(RestockRequest $request, Ingredient $ingredient, StockService $stock): RedirectResponse
    {
        try {
            $stock->restock($ingredient, $request->validated('quantity'), $request->validated('unit_cost'), $request->user(), $request->validated('note'));
        } catch (StockException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        $unit = $ingredient->unit->label();

        return to_route('owner.stock.index')->with('status', "Restock {$ingredient->name} +".qty($request->validated('quantity'))." {$unit} dicatat. Harga beli rata-rata sekarang ".rupiah($ingredient->cost_per_unit)." / {$unit}.");
    }
}
