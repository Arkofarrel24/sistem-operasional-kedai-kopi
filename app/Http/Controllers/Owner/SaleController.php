<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'cashier' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
        ]);

        $filtered = Sale::query()
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['cashier'] ?? null, fn ($query, $cashier) => $query->where('user_id', $cashier))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        $sales = $filtered->clone()
            ->with('user')
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('owner.sales.index', [
            'sales' => $sales,
            'totals' => $reports->salesTotals($filtered),
            'filters' => $filters,
            'cashiers' => User::where('role', Role::Cashier)->orderBy('name')->get(),
            'statuses' => SaleStatus::cases(),
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load(['items', 'user']);

        return view('owner.sales.show', [
            'sale' => $sale,
            'voidedBy' => $sale->voidedBy(),
        ]);
    }
}
