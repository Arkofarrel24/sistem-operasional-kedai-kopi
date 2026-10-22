<?php

namespace App\Exports\Sheets;

use Carbon\CarbonInterface;

class HourlySheet extends ReportSheet
{
    /**
     * @param  list<array{hour: int, transactions: int, revenue: string}>  $hours
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to, private array $hours)
    {
        parent::__construct($from, $to);
    }

    public function title(): string
    {
        return 'Per Jam';
    }

    protected function headings(): array
    {
        return ['Jam', 'Jumlah Transaksi', 'Omzet'];
    }

    protected function rows(): array
    {
        $first = self::HEADER_ROW + 1;
        $last = $first + count($this->hours) - 1;

        $rows = array_map(fn (array $hour) => [
            sprintf('%02d.00–%02d.59', $hour['hour'], $hour['hour']),
            $hour['transactions'],
            (float) $hour['revenue'],
        ], $this->hours);

        $rows[] = ['Total', "=SUM(B{$first}:B{$last})", "=SUM(C{$first}:C{$last})"];

        return $rows;
    }

    protected function formats(): array
    {
        return ['B' => self::COUNT, 'C' => self::RUPIAH];
    }

    protected function widths(): array
    {
        return ['A' => 16, 'B' => 18, 'C' => 18];
    }

    protected function boldRows(): array
    {
        return [self::HEADER_ROW + count($this->hours) + 1];
    }

    protected function notes(): array
    {
        return ['Jam mengikuti zona waktu Asia/Jakarta, dijumlah untuk seluruh periode. Transaksi void tidak dihitung.'];
    }
}
