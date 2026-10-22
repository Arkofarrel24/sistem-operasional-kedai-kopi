<?php

namespace App\Http\Controllers\Owner;

use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\ReportRangeRequest;
use App\Services\ReportService;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(ReportRangeRequest $request, ReportService $reports): View
    {
        [$from, $to] = [$request->from(), $request->to()];
        $hourly = $reports->hourly($from, $to);

        return view('owner.reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $reports->summary($from, $to),
            'profit' => $reports->grossProfit($from, $to),
            'waste' => $reports->waste($from, $to),
            'topProducts' => $reports->topProducts($from, $to),
            'hourly' => $hourly,
            'busiestHours' => collect($hourly)->where('transactions', '>', 0)->sortByDesc('transactions')->take(3)->pluck('hour')->all(),
        ]);
    }

    public function export(ReportRangeRequest $request): BinaryFileResponse
    {
        [$from, $to] = [$request->from(), $request->to()];

        return Excel::download(
            new SalesReportExport($from, $to),
            "laporan-kopi-nusa-{$from->toDateString()}-sd-{$to->toDateString()}.xlsx",
        );
    }
}
