<?php

use App\Models\Booking;
use App\Models\SalesGoal;
use App\Models\User;
use App\Models\Venue;
use App\Reports\Handlers\SalesGoalAttainmentReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('computes goal attainment from booked revenue owned by the salesperson', function () {
    $rep = User::factory()->create(['name' => 'Casey Rep']);
    $venue = Venue::factory()->create();

    // $50k booked counts; $20k tentative must not
    Booking::factory()->create([
        'owner_user_id' => $rep->id, 'venue_id' => $venue->id,
        'status' => 'definite', 'start_at' => Carbon::create(2026, 3, 1), 'total_cents' => 5_000_000,
    ]);
    Booking::factory()->create([
        'owner_user_id' => $rep->id, 'venue_id' => $venue->id,
        'status' => 'tentative', 'start_at' => Carbon::create(2026, 4, 1), 'total_cents' => 2_000_000,
    ]);

    SalesGoal::factory()->create(['user_id' => $rep->id, 'year' => 2026, 'month' => null, 'target_cents' => 10_000_000]);

    $result = app(SalesGoalAttainmentReport::class)->run(['year' => 2026]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['salesperson'])->toBe('Casey Rep')
        ->and($result->rows[0]['target'])->toBe('$100,000')
        ->and($result->rows[0]['actual'])->toBe('$50,000')
        ->and($result->rows[0]['attainment'])->toBe('50%');
});
