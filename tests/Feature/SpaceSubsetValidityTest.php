<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * gb1 > gb2 > {gb3, gb4}. gb3/gb4 share a parent, so picking gb2 with
 * either of {gb3, gb4} requires picking both.
 *
 * @return array<string, Space>
 */
function ballroomTree(): array
{
    $venue = Venue::factory()->create();
    $gb1 = Space::factory()->for($venue)->create(['name' => 'gb1']);
    $gb2 = Space::factory()->for($venue)->create(['name' => 'gb2', 'parent_space_id' => $gb1->id]);
    $gb3 = Space::factory()->for($venue)->create(['name' => 'gb3', 'parent_space_id' => $gb2->id]);
    $gb4 = Space::factory()->for($venue)->create(['name' => 'gb4', 'parent_space_id' => $gb2->id]);

    return ['gb1' => $gb1, 'gb2' => $gb2, 'gb3' => $gb3, 'gb4' => $gb4];
}

it('accepts the seven valid Grand Ballroom subsets', function () {
    $t = ballroomTree();
    $cases = [
        [$t['gb1']],
        [$t['gb2']],
        [$t['gb3']],
        [$t['gb4']],
        [$t['gb1'], $t['gb2']],
        [$t['gb3'], $t['gb4']],
        [$t['gb2'], $t['gb3'], $t['gb4']],
        [$t['gb1'], $t['gb2'], $t['gb3'], $t['gb4']],
    ];

    foreach ($cases as $subset) {
        $errors = Space::validateSubset(collect($subset)->pluck('id')->all());
        expect($errors)->toBe([], 'Subset '.collect($subset)->pluck('name')->implode(',').' should be valid');
    }
});

it('rejects a parent selected with only some of its children', function () {
    $t = ballroomTree();

    $errors = Space::validateSubset([$t['gb2']->id, $t['gb3']->id]);
    expect($errors)->not->toBe([]);
    expect($errors[0])->toContain('every sub-space');
});

it('rejects spaces from disconnected branches', function () {
    $t = ballroomTree();

    $errors = Space::validateSubset([$t['gb1']->id, $t['gb3']->id]);
    expect($errors)->not->toBe([]);
    expect($errors[0])->toContain('different branches');
});

it('rejects a parent + 1 of 2 children even when no other branch is involved', function () {
    $t = ballroomTree();

    $errors = Space::validateSubset([$t['gb2']->id, $t['gb4']->id]);
    expect($errors)->not->toBe([]);
});

it('rejects an invalid subset on the booking create form', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create();
    $t = ballroomTree();

    $this->actingAs($user)
        ->post('/bookings', [
            'venue_id' => $t['gb1']->venue_id,
            'client_id' => $client->id,
            'name' => 'Bad combo',
            'kind' => 'conference',
            'status' => 'tentative',
            'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(7)->setTime(16, 0)->toDateTimeString(),
            'spaces' => [$t['gb2']->id, $t['gb3']->id],
        ])
        ->assertSessionHasErrors(['spaces']);

    expect(Booking::query()->where('name', 'Bad combo')->exists())->toBeFalse();
});

it('accepts a valid contiguous subset on the booking create form', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create();
    $t = ballroomTree();

    $this->actingAs($user)
        ->post('/bookings', [
            'venue_id' => $t['gb1']->venue_id,
            'client_id' => $client->id,
            'name' => 'Section 2+3+4',
            'kind' => 'conference',
            'status' => 'tentative',
            'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(7)->setTime(16, 0)->toDateTimeString(),
            'spaces' => [$t['gb2']->id, $t['gb3']->id, $t['gb4']->id],
        ])
        ->assertRedirect();

    expect(Booking::query()->where('name', 'Section 2+3+4')->exists())->toBeTrue();
});
