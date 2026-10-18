<?php

namespace App\Http\Controllers\Owner;

use App\Exceptions\SaleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\VoidSaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;

class VoidSaleController extends Controller
{
    public function __invoke(VoidSaleRequest $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        try {
            $sales->void($sale, $request->user(), $request->validated('reason'));
        } catch (SaleException $exception) {
            return to_route('owner.sales.show', $sale)->with('error', $exception->getMessage());
        }

        return to_route('owner.sales.show', $sale)->with('status', "Transaksi {$sale->invoice_no} di-void. Stok bahan sudah dikembalikan.");
    }
}
