<?php

test('rupiah formats amounts the Indonesian way', function () {
    expect(rupiah('15000.00'))->toBe('Rp15.000')
        ->and(rupiah(95000))->toBe('Rp95.000')
        ->and(rupiah('22.50'))->toBe('Rp22,50')
        ->and(rupiah(0))->toBe('Rp0');
});

test('qty drops trailing zeros', function () {
    expect(qty('1500.00'))->toBe('1.500')
        ->and(qty('0.50'))->toBe('0,5')
        ->and(qty('18'))->toBe('18')
        ->and(qty('2500.25'))->toBe('2.500,25');
});

test('rupiah can round to whole rupiah for display', function () {
    expect(rupiah('11272721.62', whole: true))->toBe('Rp11.272.722')
        ->and(rupiah('92978.50', whole: true))->toBe('Rp92.979')
        ->and(rupiah('15000.00', whole: true))->toBe('Rp15.000')
        ->and(rupiah('22.50'))->toBe('Rp22,50');
});
