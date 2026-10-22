<?php

namespace App\Exports\Sheets;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;

class SummarySheet extends ReportSheet
{
    /**
     * @param  array{revenue: string, transactions: int, average: string, by_method: list<array{method: PaymentMethod, transactions: int, revenue: string}>}  $summary
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to, private array $summary)
    {
        parent::__construct($from, $to);
    }

    public function title(): string
    {
        return 'Rekap';
    }

    protected function headings(): array
    {
        return ['Metode Pembayaran', 'Jumlah Transaksi', 'Omzet', 'Porsi Omzet'];
    }

    protected function rows(): array
    {
        $first = self::HEADER_ROW + 1;
        $methods = count($this->summary['by_method']);
        $last = $first + $methods - 1;
        $total = $last + 1;

        $rows = [];
        foreach ($this->summary['by_method'] as $index => $row) {
            $r = $first + $index;
            $rows[] = [$row['method']->label(), $row['transactions'], (float) $row['revenue'], "=IFERROR(C{$r}/C\${$total},0)"];
        }

        $rows[] = ['Total', "=SUM(B{$first}:B{$last})", "=SUM(C{$first}:C{$last})", "=IFERROR(C{$total}/C{$total},0)"];
        $rows[] = ['Rata-rata per transaksi', null, "=IFERROR(C{$total}/B{$total},0)", null];

        return $rows;
    }

    protected function formats(): array
    {
        return ['B' => self::COUNT, 'C' => self::RUPIAH, 'D' => self::PERCENT];
    }

    protected function widths(): array
    {
        return ['A' => 26, 'B' => 18, 'C' => 18, 'D' => 14];
    }

    protected function boldRows(): array
    {
        $total = self::HEADER_ROW + count($this->summary['by_method']) + 1;

        return [$total, $total + 1];
    }

    protected function notes(): array
    {
        return [
            'Transaksi void tidak dihitung. Tanggal mengikuti zona waktu Asia/Jakarta, tanggal akhir ikut dihitung.',
        ];
    }
}
