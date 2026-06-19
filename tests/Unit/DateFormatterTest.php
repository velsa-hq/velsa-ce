<?php

use App\Support\DateFormatter;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->dt = CarbonImmutable::create(2026, 6, 1, 9, 5, 0); // mon jun 1 2026 09:05
});

it('formats each named date shape exactly', function () {
    expect(DateFormatter::editDateTime($this->dt))->toBe('2026-06-01T09:05')
        ->and(DateFormatter::dayLabel($this->dt))->toBe('Monday, Jun 1, 2026')
        ->and(DateFormatter::timeOnly($this->dt))->toBe('9:05 AM')
        ->and(DateFormatter::dateTime($this->dt))->toBe('Jun 1, 2026 · 9:05 AM')
        ->and(DateFormatter::dateTimeWithDay($this->dt))->toBe('Mon, Jun 1, 2026 · 9:05 AM')
        ->and(DateFormatter::reportStamp($this->dt))->toBe('Jun 1, 2026 9:05 AM')
        ->and(DateFormatter::fileStamp($this->dt))->toBe('2026-06-01_090500');
});

it('is null-safe for nullable dates', function () {
    expect(DateFormatter::editDateTime(null))->toBeNull()
        ->and(DateFormatter::dayLabel(null))->toBeNull()
        ->and(DateFormatter::timeOnly(null))->toBeNull()
        ->and(DateFormatter::dateTime(null))->toBeNull();
});

it('defaults fileStamp to now', function () {
    expect(DateFormatter::fileStamp())->toMatch('/^\d{4}-\d{2}-\d{2}_\d{6}$/');
});
