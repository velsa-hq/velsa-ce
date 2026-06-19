<?php

use App\Enums\BookingStatus;
use App\Enums\HoldRank;
use App\Mail\HoldExpired;
use App\Mail\HoldPromoted;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\User;
use App\Services\Bookings\HoldExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** A Hold booking on $space for a fixed Sept-1 window. */
function makeHold(Space $space, ?string $rank, mixed $expiresAt, User $owner): Booking
{
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Hold->value,
        'hold_rank' => $rank,
        'hold_expires_at' => $expiresAt,
        'owner_user_id' => $owner->id,
        'venue_id' => $space->venue_id,
        'start_at' => '2026-09-01 09:00:00',
        'end_at' => '2026-09-01 17:00:00',
    ]);

    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-09-01 09:00:00',
        'end_at' => '2026-09-01 17:00:00',
        'setup_minutes_before' => 0,
        'teardown_minutes_after' => 0,
    ]);

    return $booking;
}

it('releases an expired hold and notifies its owner', function () {
    Mail::fake();
    $space = Space::factory()->create();
    $hold = makeHold($space, '1st', now()->subDay(), User::factory()->create());

    $result = app(HoldExpiryService::class)->expireDue();

    expect($result['expired'])->toBe(1)
        ->and($hold->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($hold->cancel_reason)->toBe('Hold expired')
        ->and($hold->cancelled_at)->not->toBeNull();

    Mail::assertQueued(HoldExpired::class);
});

it('promotes the next hold to first when the first expires', function () {
    Mail::fake();
    $space = Space::factory()->create();
    $first = makeHold($space, '1st', now()->subDay(), User::factory()->create());
    $second = makeHold($space, '2nd', now()->addDays(5), User::factory()->create());

    $result = app(HoldExpiryService::class)->expireDue();

    expect($result['expired'])->toBe(1)
        ->and($result['promoted'])->toBe(1)
        ->and($second->refresh()->hold_rank)->toBe(HoldRank::First)
        ->and($second->status)->toBe(BookingStatus::Hold);

    Mail::assertQueued(HoldPromoted::class);
});

it('leaves a hold that has not yet expired alone', function () {
    $space = Space::factory()->create();
    $hold = makeHold($space, '1st', now()->addDays(3), User::factory()->create());

    $result = app(HoldExpiryService::class)->expireDue();

    expect($result['expired'])->toBe(0)
        ->and($hold->refresh()->status)->toBe(BookingStatus::Hold);
});

it('does not promote anyone when an unranked hold expires', function () {
    Mail::fake();
    $space = Space::factory()->create();
    makeHold($space, null, now()->subDay(), User::factory()->create());
    $second = makeHold($space, '2nd', now()->addDays(5), User::factory()->create());

    $result = app(HoldExpiryService::class)->expireDue();

    expect($result['expired'])->toBe(1)
        ->and($result['promoted'])->toBe(0)
        ->and($second->refresh()->hold_rank)->toBe(HoldRank::Second);

    Mail::assertNotQueued(HoldPromoted::class);
});

it('runs as the scheduled command', function () {
    $this->artisan('bookings:expire-holds')->assertExitCode(0);
});
