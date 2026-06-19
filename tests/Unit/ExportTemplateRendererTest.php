<?php

use App\Services\Accounting\ValueFormatter;

it('passes through raw strings when no mask is supplied', function () {
    expect(ValueFormatter::apply('hello', null))->toBe('hello')
        ->and(ValueFormatter::apply('hello', ''))->toBe('hello');
});

it('formats dates with the date mask', function () {
    $date = new DateTimeImmutable('2026-05-27 14:30:00');
    expect(ValueFormatter::apply($date, 'date:Y-m-d'))->toBe('2026-05-27')
        ->and(ValueFormatter::apply($date, 'date:Ymd'))->toBe('20260527')
        ->and(ValueFormatter::apply('2026-05-27', 'date:m/d/Y'))->toBe('05/27/2026');
});

it('renders cents as dollars with money:dot', function () {
    expect(ValueFormatter::apply(12345, 'money:dot'))->toBe('123.45')
        ->and(ValueFormatter::apply(0, 'money:dot'))->toBe('0.00')
        ->and(ValueFormatter::apply(1, 'money:dot'))->toBe('0.01');
});

it('preserves cents-as-integer with money:int', function () {
    expect(ValueFormatter::apply(12345, 'money:int'))->toBe('12345');
});

it('rounds to whole dollars with money:dollars', function () {
    expect(ValueFormatter::apply(12345, 'money:dollars'))->toBe('123')
        ->and(ValueFormatter::apply(12550, 'money:dollars'))->toBe('126');
});

it('renders signed money for negative cents', function () {
    expect(ValueFormatter::apply(-12345, 'money:signed'))->toBe('-123.45')
        ->and(ValueFormatter::apply(12345, 'money:signed'))->toBe('123.45');
});

it('pads with zeros to a width', function () {
    expect(ValueFormatter::apply(42, 'pad-zero:6'))->toBe('000042')
        ->and(ValueFormatter::apply('A', 'pad-zero:4'))->toBe('000A');
});

it('truncates strings to a maximum width', function () {
    expect(ValueFormatter::apply('hello world', 'truncate:5'))->toBe('hello');
});

it('chains masks left-to-right with the pipe operator', function () {
    expect(ValueFormatter::apply('hello', 'upper|truncate:3'))->toBe('HEL');
});

it('throws on an unknown format mask', function () {
    ValueFormatter::apply('hello', 'nonsense:42');
})->throws(InvalidArgumentException::class);

it('csv-escapes values that contain the delimiter, quote, or newline', function () {
    expect(ValueFormatter::csvEscape('plain', ',', '"'))->toBe('plain')
        ->and(ValueFormatter::csvEscape('has,comma', ',', '"'))->toBe('"has,comma"')
        ->and(ValueFormatter::csvEscape('has "quote"', ',', '"'))->toBe('"has ""quote"""')
        ->and(ValueFormatter::csvEscape("multi\nline", ',', '"'))->toBe("\"multi\nline\"");
});

it('pads and truncates to an exact fixed width', function () {
    expect(ValueFormatter::fixedWidth('ab', 5, 'left', ' '))->toBe('ab   ')
        ->and(ValueFormatter::fixedWidth('ab', 5, 'right', '0'))->toBe('000ab')
        ->and(ValueFormatter::fixedWidth('abcdef', 4))->toBe('abcd');
});
