<?php

use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/ReportScenario.php';

beforeEach(function () {
    $this->data = seedReportScenario();
    $this->travelTo(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
    $this->owner = $this->data['owner'];
});

test('report defaults to this month so far', function () {
    $this->actingAs($this->owner)->get(route('owner.reports.index'))
        ->assertOk()
        ->assertSee('1 Okt 2026 sampai 15 Okt 2026')
        ->assertSee(['Rp172.000', 'Rp56.400', 'Rp115.600', '67,2%', 'Rp34.000']) // revenue, COGS, profit, margin, waste
        ->assertSee(route('owner.reports.export', ['from' => '2026-10-01', 'to' => '2026-10-15']));
});

test('report shows the hand-computed figures for one inclusive day', function () {
    $this->actingAs($this->owner)->get(route('owner.reports.index', ['from' => '2026-10-01', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertSeeInOrder(['Rekap penjualan', 'Rp147.000', '4', 'Rp36.750'])
        ->assertSeeInOrder(['Tunai', '2 transaksi', 'Rp86.000', 'QRIS', '1 transaksi', 'Rp36.000', 'Transfer', '1 transaksi', 'Rp25.000'])
        ->assertSeeInOrder(['Laba kotor', 'Margin', '67,3%', 'Rp147.000', 'Rp48.000', 'Rp99.000'])
        ->assertSeeInOrder(['Nilai bahan terbuang', 'Rp9.000', 'Susu UHT'])
        ->assertSeeInOrder(['Menu terlaris', 'Latte', '3', 'Rp75.000', 'Americano', '4', 'Rp72.000'])
        ->assertSee('Paling ramai pukul 08.00');
});

test('report rejects an invalid range', function (array $query, string $message) {
    $this->actingAs($this->owner)
        ->get(route('owner.reports.index', $query))
        ->assertRedirect(route('owner.reports.index'))
        ->assertSessionHasErrors(['to' => $message]);
})->with([
    'end before start' => [['from' => '2026-10-05', 'to' => '2026-10-01'], 'Sampai tanggal harus tanggal setelah atau sama dengan dari tanggal.'],
    'longer than a year' => [['from' => '2025-01-01', 'to' => '2026-10-01'], 'Rentang laporan maksimal 366 hari.'],
]);

test('excel export has the four sheets with numeric, formatted figures', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('owner.reports.export', ['from' => '2026-10-01', 'to' => '2026-10-02']))
        ->assertOk()
        ->assertDownload('laporan-kopi-nusa-2026-10-01-sd-2026-10-02.xlsx');

    $book = IOFactory::load($response->getFile()->getPathname());
    expect($book->getSheetNames())->toBe(['Rekap', 'Menu Terlaris', 'Per Jam', 'Laba Kotor']);

    foreach ($book->getAllSheets() as $sheet) {
        expect($sheet->getCell('A1')->getValue())->toBe('Kopi Nusa: Laporan Penjualan')
            ->and($sheet->getCell('A2')->getValue())->toBe('Periode: 1 Okt 2026 s/d 2 Okt 2026')
            ->and($sheet->getStyle('A4')->getFont()->getBold())->toBeTrue()
            ->and($sheet->getStyle('A5')->getFont()->getName())->toBe('Arial');

        // No formula errors anywhere.
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                expect((string) $cell->getCalculatedValue())->not->toStartWith('#');
            }
        }
    }

    $rekap = $book->getSheetByName('Rekap');
    expect($rekap->getCell('A5')->getValue())->toBe('Tunai')
        ->and($rekap->getCell('C5')->getDataType())->toBe(DataType::TYPE_NUMERIC)
        ->and($rekap->getCell('C5')->getValue())->toEqual(86000)
        ->and($rekap->getStyle('C5')->getNumberFormat()->getFormatCode())->toContain('"Rp"#,##0')
        ->and($rekap->getCell('C8')->getValue())->toBe('=SUM(C5:C7)')
        ->and($rekap->getCell('C8')->getCalculatedValue())->toEqual(172000)
        ->and($rekap->getCell('B8')->getCalculatedValue())->toEqual(5)
        ->and($rekap->getCell('C9')->getCalculatedValue())->toEqual(34400)
        ->and($rekap->getStyle('D5')->getNumberFormat()->getFormatCode())->toContain('0.0%');

    $top = $book->getSheetByName('Menu Terlaris');
    expect([$top->getCell('B5')->getValue(), $top->getCell('C5')->getValue(), $top->getCell('D5')->getValue()])->toEqual(['Latte', 4, 100000])
        ->and(round($top->getCell('E5')->getCalculatedValue(), 4))->toEqual(0.5814);

    $hourly = $book->getSheetByName('Per Jam');
    expect($hourly->getCell('A13')->getValue())->toBe('08.00–08.59')
        ->and($hourly->getCell('B13')->getValue())->toEqual(2)
        ->and($hourly->getCell('B5')->getValue())->toEqual(1) // 00:10 on 2 Oct
        ->and($hourly->getCell('B21')->getValue())->toEqual(0) // 16:00, voided
        ->and($hourly->getCell('B29')->getCalculatedValue())->toEqual(5);

    $profit = $book->getSheetByName('Laba Kotor');
    expect($profit->getCell('B5')->getValue())->toEqual(172000)
        ->and($profit->getCell('B6')->getValue())->toEqual(56400)
        ->and($profit->getCell('B7')->getCalculatedValue())->toEqual(115600)
        ->and(round($profit->getCell('B8')->getCalculatedValue(), 4))->toEqual(0.6721)
        ->and($profit->getCell('B9')->getCalculatedValue())->toEqual(34000);
});

test('cashier gets 403 on the dashboard, reports and export', function (string $route) {
    $this->actingAs(User::factory()->cashier()->create())->get(route($route))->assertForbidden();
})->with(['owner.dashboard', 'owner.reports.index', 'owner.reports.export']);

test('guest is redirected to login from the dashboard, reports and export', function (string $route) {
    $this->get(route($route))->assertRedirect('/login');
})->with(['owner.dashboard', 'owner.reports.index', 'owner.reports.export']);
