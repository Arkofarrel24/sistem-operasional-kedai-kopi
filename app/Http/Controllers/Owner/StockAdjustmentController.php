<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\StockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StockAdjustmentRequest;
use App\Models\Ingredient;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function create(Ingredient $ingredient): View
    {
        return view('owner.stock.adjustment', compact('ingredient'));
    }

    public function store(StockAdjustmentRequest $request, Ingredient $ingredient, StockService $stock): RedirectResponse
    {
        try {
            $movement = $stock->adjustToCount($ingredient, $request->validated('counted_stock'), $request->user(), $request->validated('reason'));
        } catch (StockException $exception) {
            return back()->withInput()->withErrors(['counted_stock' => $exception->getMessage()]);
        }

        $unit = $ingredient->unit->label();
        $message = $movement
            ? "Penyesuaian {$ingredient->name} dicatat: selisih ".(bccomp($movement->quantity, '0', 2) > 0 ? '+' : '').qty($movement->quantity)." {$unit}."
            : "Stok {$ingredient->name} sudah sesuai hasil hitung. Tidak ada yang dicatat.";

        return to_route('owner.stock.index')->with('status', $message);
    }
}
