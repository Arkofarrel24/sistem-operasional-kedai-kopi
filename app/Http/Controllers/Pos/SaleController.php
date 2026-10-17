<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\View\View;

class SaleController extends Controller
{
    /**
     * Printable receipt; ownership is checked by SalePolicy via the route's can middleware.
     */
    public function show(Sale $sale): View
    {
        $sale->load(['items', 'user']);

        return view('pos.receipt', compact('sale'));
    }
}
