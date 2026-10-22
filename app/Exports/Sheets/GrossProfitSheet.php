<?php

namespace App\Exports\Sheets;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GrossProfitSheet extends ReportSheet
{
    /**
     * Row of the waste table header, below the profit figures.
     */
    private const WASTE_HEADER_ROW = 11;

    /**
     * @param  array{revenue: string, cogs: string, gross_profit: string, margin: ?string}  $profit
     * @param  array{total: string, items: Collection}  $waste
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to, private array $profit, private array $waste)
    {
        parent::__construct($from, $to);
    }

    public function title(): string
    {
        return 'Laba Kotor';
    }

    protected function headings(): array
    {
        return ['Keterangan', 'Nilai', '', ''];
    }

    /**
     * Rows 5–8: profit figures; row 9: waste total; row 11: waste header; row 12+: waste per ingredient.
     */
    protected function rows(): array
    {
        $wasteFirst = self::WASTE_HEADER_ROW + 1;
        $wasteLast = $wasteFirst + max($this->waste['items']->count(), 1) - 1;

        $rows = [
            ['Omzet', (float) $this->profit['revenue']],
            ['HPP (harga beli bahan terjual)', (float) $this->profit['cogs']],
            ['Laba kotor', '=B5-B6'],
            ['Margin laba kotor', '=IFERROR(B7/B5,0)'],
            ['Nilai waste', $this->waste['items']->isEmpty() ? 0 : "=SUM(D{$wasteFirst}:D{$wasteLast})"],
            [null],
            ['Bahan terbuang (waste)', 'Jumlah', 'Satuan', 'Nilai'],
        ];

        foreach ($this->waste['items'] as $item) {
            $rows[] = [$item->name, (float) $item->quantity, $item->unit, (float) $item->value];
        }

        if ($this->waste['items']->isEmpty()) {
            $rows[] = ['Tidak ada waste pada periode ini', null, null, 0];
        }

        return $rows;
    }

    protected function formats(): array
    {
        return [];
    }

    protected function widths(): array
    {
        return ['A' => 34, 'B' => 18, 'C' => 10, 'D' => 16];
    }

    protected function notes(): array
    {
        return [
            'HPP = jumlah bahan terjual x harga beli bahan saat transaksi terjadi (tidak berubah walau harga bahan naik kemudian).',
            'Transaksi void tidak dihitung. Nilai waste = jumlah terbuang x harga beli saat waste dicatat.',
        ];
    }

    protected function layout(Worksheet $sheet): void
    {
        parent::layout($sheet);

        $wasteFirst = self::WASTE_HEADER_ROW + 1;
        $wasteLast = $wasteFirst + max($this->waste['items']->count(), 1) - 1;

        $sheet->getStyle('B5:B7')->getNumberFormat()->setFormatCode(self::RUPIAH);
        $sheet->getStyle('B8')->getNumberFormat()->setFormatCode(self::PERCENT);
        $sheet->getStyle('B9')->getNumberFormat()->setFormatCode(self::RUPIAH);
        $sheet->getStyle('A7:B7')->getFont()->setBold(true);

        $header = $sheet->getStyle('A'.self::WASTE_HEADER_ROW.':D'.self::WASTE_HEADER_ROW);
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E5E4');
        $sheet->getStyle("B{$wasteFirst}:B{$wasteLast}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("D{$wasteFirst}:D{$wasteLast}")->getNumberFormat()->setFormatCode(self::RUPIAH);
    }
}
