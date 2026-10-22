<?php

namespace App\Exports\Sheets;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TopProductsSheet extends ReportSheet
{
    public function __construct(CarbonInterface $from, CarbonInterface $to, private Collection $products)
    {
        parent::__construct($from, $to);
    }

    public function title(): string
    {
        return 'Menu Terlaris';
    }

    protected function headings(): array
    {
        return ['No', 'Menu', 'Terjual (porsi)', 'Omzet', 'Porsi Omzet'];
    }

    protected function rows(): array
    {
        $first = self::HEADER_ROW + 1;
        $last = $first + $this->products->count() - 1;
        $total = $last + 1;

        $rows = $this->products->values()->map(function (object $product, int $index) use ($first, $total) {
            $r = $first + $index;

            return [$index + 1, $product->name, $product->quantity, (float) $product->revenue, "=IFERROR(D{$r}/D\${$total},0)"];
        })->all();

        $rows[] = $this->products->isEmpty()
            ? [null, 'Total', 0, 0, 0]
            : [null, 'Total', "=SUM(C{$first}:C{$last})", "=SUM(D{$first}:D{$last})", "=IFERROR(D{$total}/D{$total},0)"];

        return $rows;
    }

    protected function formats(): array
    {
        return ['A' => '0', 'C' => self::COUNT, 'D' => self::RUPIAH, 'E' => self::PERCENT];
    }

    protected function widths(): array
    {
        return ['A' => 6, 'B' => 30, 'C' => 16, 'D' => 18, 'E' => 14];
    }

    protected function boldRows(): array
    {
        return [self::HEADER_ROW + $this->products->count() + 1];
    }

    protected function notes(): array
    {
        return ['Diurutkan dari omzet terbesar. Omzet memakai harga saat transaksi; transaksi void tidak dihitung.'];
    }
}
