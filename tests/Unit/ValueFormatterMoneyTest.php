<?php

use App\Services\Accounting\ValueFormatter;

it('formats dollars with thousands separators and two decimals', function () {
    expect(ValueFormatter::dollars(1_500_000))->toBe('15,000.00')
        ->and(ValueFormatter::dollars(0))->toBe('0.00')
        ->and(ValueFormatter::dollars(99))->toBe('0.99')
        ->and(ValueFormatter::dollars(-1_500_000))->toBe('-15,000.00')
        ->and(ValueFormatter::dollars(100))->toBe('1.00');
});

it('formats usd with a leading dollar sign', function () {
    expect(ValueFormatter::usd(1_500_000))->toBe('$15,000.00')
        ->and(ValueFormatter::usd(0))->toBe('$0.00')
        ->and(ValueFormatter::usd(-2550))->toBe('$-25.50');
});

it('formats usdRounded with no decimals', function () {
    expect(ValueFormatter::usdRounded(1_500_000))->toBe('$15,000')
        ->and(ValueFormatter::usdRounded(1550))->toBe('$16')   // 15.50 -> 16
        ->and(ValueFormatter::usdRounded(0))->toBe('$0');
});

it('matches the inline number_format it replaces', function () {
    foreach ([0, 99, 100, 1234567, -8800] as $cents) {
        expect(ValueFormatter::dollars($cents))->toBe(number_format($cents / 100, 2));
    }
});
