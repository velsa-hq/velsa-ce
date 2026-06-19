<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a unique reference on create when none is provided', function () {
    $a = Booking::factory()->create(['reference' => null]);
    $b = Booking::factory()->create(['reference' => null]);

    expect($a->reference)->toStartWith('BK-')
        ->and($a->reference)->not->toBe($b->reference);
});

it('honors an explicit reference when provided', function () {
    $booking = Booking::factory()->create(['reference' => 'BK-CUSTOM-001']);

    expect($booking->reference)->toBe('BK-CUSTOM-001');
});

it('casts status to BookingStatus enum', function () {
    $booking = Booking::factory()->definite()->create();

    expect($booking->status)->toBe(BookingStatus::Definite);
});

it('auto-sets cancelled_at when transitioning into Cancelled', function () {
    $booking = Booking::factory()->definite()->create(['cancelled_at' => null]);

    $booking->update(['status' => BookingStatus::Cancelled->value]);

    expect($booking->cancelled_at)->not->toBeNull();
});

it('scopes ->withStatus() to one or more statuses', function () {
    Booking::factory()->withStatus(BookingStatus::Inquiry)->count(3)->create();
    Booking::factory()->withStatus(BookingStatus::Definite)->count(2)->create();
    Booking::factory()->withStatus(BookingStatus::Cancelled)->create();

    expect(Booking::query()->withStatus(BookingStatus::Definite)->count())->toBe(2)
        ->and(Booking::query()->withStatus(BookingStatus::Inquiry, BookingStatus::Definite)->count())->toBe(5);
});

it('exposes BookingStatus::blocksOverlap() correctly per enum case', function () {
    expect(BookingStatus::Definite->blocksOverlap())->toBeTrue()
        ->and(BookingStatus::Completed->blocksOverlap())->toBeTrue()
        ->and(BookingStatus::Tentative->blocksOverlap())->toBeFalse()
        ->and(BookingStatus::Hold->blocksOverlap())->toBeFalse()
        ->and(BookingStatus::Inquiry->blocksOverlap())->toBeFalse()
        ->and(BookingStatus::Cancelled->blocksOverlap())->toBeFalse();
});
