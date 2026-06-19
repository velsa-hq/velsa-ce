<?php

use App\Models\Booking;
use App\Models\JournalEntry;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\SystemSettings\SystemSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->venueA = Venue::factory()->create();
    $this->venueB = Venue::factory()->create();
    Booking::factory()->create(['venue_id' => $this->venueA->id]);
    Booking::factory()->create(['venue_id' => $this->venueB->id]);
});

function isolation(bool $on): void
{
    app(SystemSettings::class)->set('operations.venue_isolation', $on);
}

it('shows all venues when isolation is OFF (default)', function () {
    $user = User::factory()->create();
    $user->assignRoleAt($this->venueA, 'sales_rep'); // venue A only, no view_all

    $this->actingAs($user);
    expect(Booking::count())->toBe(2);
});

it('scopes a non-privileged user to their own venues when isolation is ON', function () {
    isolation(true);
    $user = User::factory()->create();
    $user->assignRoleAt($this->venueA, 'sales_rep'); // venue A only, no view_all

    $this->actingAs($user);
    expect(Booking::count())->toBe(1)
        ->and(Booking::sole()->venue_id)->toBe($this->venueA->id);
});

it('lets a venues.view_all holder see every venue when isolation is ON', function () {
    isolation(true);
    $admin = User::factory()->create();
    $admin->assignRoleAt($this->venueA, 'org_admin'); // has view_all

    $this->actingAs($admin);
    expect(Booking::count())->toBe(2);
});

it('does not scope when there is no authenticated user (CLI / queue)', function () {
    isolation(true);
    // no actingAs - scope must no-op so background work isn't silently filtered
    expect(Booking::count())->toBe(2);
});

it('hides other venues across multiple scoped models', function () {
    isolation(true);
    $user = User::factory()->create();
    $user->assignRoleAt($this->venueB, 'sales_rep');

    $this->actingAs($user);
    expect(Booking::pluck('venue_id')->all())->toBe([$this->venueB->id]);
});

it('scopes a non-Booking model (spaces) to assigned venues when isolation is ON', function () {
    isolation(true);
    Space::factory()->for($this->venueA)->create();
    Space::factory()->for($this->venueB)->create();

    $user = User::factory()->create();
    $user->assignRoleAt($this->venueB, 'sales_rep'); // venue B only, no view_all
    $this->actingAs($user);

    expect(Space::pluck('venue_id')->all())->toBe([$this->venueB->id]);
});

it('keeps org-level (null-venue) rows visible under isolation', function () {
    $this->seed(ChartOfAccountsSeeder::class); // post hook resolves account_code

    JournalEntry::factory()->create(['venue_id' => null]);              // org-level
    JournalEntry::factory()->create(['venue_id' => $this->venueB->id]);

    isolation(true);
    $user = User::factory()->create();
    $user->assignRoleAt($this->venueA, 'sales_rep'); // venue A only

    $this->actingAs($user);
    expect(JournalEntry::pluck('venue_id')->all())->toBe([null]);
});
