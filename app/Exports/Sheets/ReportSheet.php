<?php

namespace App\Exports\Sheets;

use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared layout of every report sheet:
 *   row 1  title (business name), row 2  period, row 3  empty, row 4  table header, row 5+  data.
 * Raw figures are written as numbers (zero stays 0, shown as "-"); derived figures (totals, shares,
 * profit) as Excel formulas.
 */
abstract class ReportSheet implements FromArray, WithEvents, WithPreCalculateFormulas, WithStrictNullComparison, WithTitle
{
    public const RUPIAH = '"Rp"#,##0;-"Rp"#,##0;"-"';

    public const COUNT = '#,##0;-#,##0;"-"';

    public const PERCENT = '0.0%;-0.0%;"-"';

    protected const HEADER_ROW = 4;

    public function __construct(
        protected CarbonInterface $from,
        protected CarbonInterface $to,
    ) {}

    /**
     * @return list<string>
     */
    abstract protected function headings(): array;

    /**
     * Table rows below the header (numbers as int/float, formulas as "=..." strings).
     *
     * @return list<list<mixed>>
     */
    abstract protected function rows(): array;

    /**
     * Number formats per column letter for the data rows, e.g. ['C' => self::RUPIAH].
     *
     * @return array<string, string>
     */
    abstract protected function formats(): array;

    /**
     * @return array<string, float>
     */
    abstract protected function widths(): array;

    /**
     * Notes shown under the table (assumptions behind the figures).
     *
     * @return list<string>
     */
    protected function notes(): array
    {
        return [];
    }

    /**
     * Rows (absolute numbers) to render bold, e.g. a total row.
     *
     * @return list<int>
     */
    protected function boldRows(): array
    {
        return [];
    }

    public function array(): array
    {
        $rows = [
            [config('app.name').': Laporan Penjualan'],
            ['Periode: '.$this->from->translatedFormat('j M Y').' s/d '.$this->to->translatedFormat('j M Y')],
            [null], // empty rows must hold a cell, or they are dropped and every row below shifts up
            $this->headings(),
            ...$this->rows(),
        ];

        if ($this->notes() !== []) {
            $rows[] = [null];
            foreach ($this->notes() as $note) {
                $rows[] = [$note];
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->layout($sheet);
            },
        ];
    }

    protected function layout(Worksheet $sheet): void
    {
        $lastColumn = chr(ord('A') + count($this->headings()) - 1);
        $firstData = self::HEADER_ROW + 1;
        $lastData = self::HEADER_ROW + count($this->rows());

        $sheet->getStyle('A1:'.$sheet->getHighestColumn().$sheet->getHighestRow())->getFont()->setName('Arial')->setSize(10);

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $header = $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastColumn.self::HEADER_ROW);
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7E5E4');
        $sheet->freezePane('A'.$firstData);

        if ($lastData >= $firstData) {
            foreach ($this->formats() as $column => $format) {
                $sheet->getStyle("{$column}{$firstData}:{$column}{$lastData}")->getNumberFormat()->setFormatCode($format);
            }
        }

        foreach ($this->boldRows() as $row) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getFont()->setBold(true);
        }

        foreach ($this->widths() as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $notesStart = $lastData + 2;
        foreach (array_keys($this->notes()) as $offset) {
            $sheet->getStyle('A'.($notesStart + $offset))->getFont()->setItalic(true)->getColor()->setRGB('57534E');
        }
    }
}
