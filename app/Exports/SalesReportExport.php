<?php

namespace App\Exports;

use App\Exports\Sheets\GrossProfitSheet;
use App\Exports\Sheets\HourlySheet;
use App\Exports\Sheets\SummarySheet;
use App\Exports\Sheets\TopProductsSheet;
use App\Services\ReportService;
use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Sales report workbook for a date range; same figures as the report page (ReportService).
 */
class SalesReportExport implements Export, WithMultipleSheets
{
    public function __construct(
        private CarbonInterface $from,
        private CarbonInterface $to,
    ) {}

    public function sheets(): array
    {
        $reports = app(ReportService::class);

        return [
            new SummarySheet($this->from, $this->to, $reports->summary($this->from, $this->to)),
            new TopProductsSheet($this->from, $this->to, $reports->topProducts($this->from, $this->to)),
            new HourlySheet($this->from, $this->to, $reports->hourly($this->from, $this->to)),
            new GrossProfitSheet($this->from, $this->to, $reports->grossProfit($this->from, $this->to), $reports->waste($this->from, $this->to)),
        ];
    }
}
