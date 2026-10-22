<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public const CHART_DAYS = 30;

    public function __invoke(ReportService $reports): View
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        // Compare today so far with the same weekday last week up to the same time of day.
        $current = $reports->summaryBetween($today, $now);
        $lastWeek = $reports->summaryBetween($today->subWeek(), $now->subWeek());

        $daily = $reports->dailyRevenue($today->subDays(self::CHART_DAYS - 1), $today);

        return view('owner.dashboard', [
            'now' => $now,
            'today' => $current,
            'lastWeek' => $lastWeek,
            'changes' => [
                'revenue' => $this->change($current['revenue'], $lastWeek['revenue']),
                'transactions' => $this->change((string) $current['transactions'], (string) $lastWeek['transactions']),
                'average' => $this->change($current['average'], $lastWeek['average']),
            ],
            'lowStock' => $reports->lowStockIngredients(),
            'chart' => array_map(fn (array $day) => [
                'label' => CarbonImmutable::parse($day['date'])->translatedFormat('j M'),
                'revenue' => (float) $day['revenue'],
                'transactions' => $day['transactions'],
            ], $daily),
            'weekStart' => $today->startOfWeek(),
            'topProducts' => $reports->topProducts($today->startOfWeek(), $today, 5),
        ]);
    }

    /**
     * Relative change as a fraction string ("0.1250" = +12.5 %), or null when there is nothing to compare with.
     */
    private function change(string $current, string $previous): ?string
    {
        if (bccomp($previous, '0', 2) === 0) {
            return null;
        }

        return bcdiv(bcsub($current, $previous, 2), $previous, 4);
    }
}
