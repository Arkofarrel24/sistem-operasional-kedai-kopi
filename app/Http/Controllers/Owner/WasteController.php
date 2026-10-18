<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\WasteRequest;
use App\Models\Ingredient;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WasteController extends Controller
{
    public function create(Ingredient $ingredient): View
    {
        return view('owner.stock.waste', compact('ingredient'));
    }

    public function store(WasteRequest $request, Ingredient $ingredient, StockService $stock): RedirectResponse
    {
        try {
            $stock->recordWaste($ingredient, $request->validated('quantity'), $request->user(), $request->validated('reason'));
        } catch (StockException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        return to_route('owner.stock.index')->with('status', "Waste {$ingredient->name} ".qty($request->validated('quantity'))." {$ingredient->unit->label()} dicatat.");
    }
}
