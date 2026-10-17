<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleHistoryController extends Controller
{
    /**
     * The logged-in cashier's own sales of today (Asia/Jakarta).
     */
    public function index(Request $request): View
    {
        $sales = $request->user()->sales()
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->get();

        return view('pos.history', compact('sales'));
    }
}
