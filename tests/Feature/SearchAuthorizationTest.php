<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

// global search must honor the same RBAC as direct navigation
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->venue = Venue::factory()->create();
    Client::factory()->create(['name' => 'Zephyr Corporation']);
    Booking::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Zephyr Gala',
        'status' => BookingStatus::Definite->value,
    ]);
});

function searchKeys(TestCase $test, User $user): array
{
    return collect($test->actingAs($user)->getJson('/search?q=Zephyr')->json('groups'))
        ->pluck('key')->all();
}

it('omits result types the user lacks permission for', function () {
    $user = User::factory()->create();
    // event_coordinator has bookings.view but not clients/accounting/contracts/exhibitors
    $user->assignRoleAt($this->venue, 'event_coordinator');

    $keys = searchKeys($this, $user);

    expect($keys)->toContain('bookings')
        ->not->toContain('clients')
        ->not->toContain('invoices')
        ->not->toContain('contracts')
        ->not->toContain('exhibitors');
});

it('includes a result type once the user has its permission', function () {
    $admin = User::factory()->create();
    $admin->assignRoleAt($this->venue, 'super_admin');

    $keys = searchKeys($this, $admin);

    // client record matches and is gated by permission, not a failed match
    expect($keys)->toContain('clients')
        ->toContain('bookings');
});
