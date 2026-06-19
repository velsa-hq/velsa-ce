<?php

use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\SpaceConstraintsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
    $this->space = Space::factory()->create();
});

it('shows the constraint editor page with current constraints', function () {
    $this->space->update([
        'constraints_json' => [
            ['id' => 'c1', 'kind' => 'wall', 'x' => 100, 'y' => 100, 'width_ft' => 10, 'height_ft' => 0.5, 'rotation' => 0],
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->get("/admin/spaces/{$this->space->id}/constraints");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/spaces/constraints')
        ->where('space.id', $this->space->id)
        ->has('constraints', 1)
        ->where('constraints.0.kind', 'wall'));
});

it('persists submitted constraints to the space', function () {
    $payload = [
        'constraints' => [
            ['id' => 'c1', 'kind' => 'wall', 'x' => 50, 'y' => 50, 'width_ft' => 20, 'height_ft' => 0.5, 'rotation' => 0],
            ['id' => 'c2', 'kind' => 'column', 'x' => 200, 'y' => 200, 'width_ft' => 2, 'height_ft' => 2],
            ['id' => 'c3', 'kind' => 'outlet', 'x' => 300, 'y' => 100, 'width_ft' => 0.6, 'height_ft' => 0.6, 'label' => 'AV'],
        ],
    ];

    $this->actingAs($this->user)
        ->post("/admin/spaces/{$this->space->id}/constraints", $payload)
        ->assertRedirect();

    $fresh = $this->space->fresh();
    expect($fresh->constraints_json)->toHaveCount(3)
        ->and($fresh->constraints_json[2]['label'])->toBe('AV');
});

it('rejects unknown constraint kinds', function () {
    $this->actingAs($this->user)
        ->post("/admin/spaces/{$this->space->id}/constraints", [
            'constraints' => [
                ['id' => 'c1', 'kind' => 'helipad', 'x' => 0, 'y' => 0, 'width_ft' => 10, 'height_ft' => 10],
            ],
        ])
        ->assertSessionHasErrors('constraints.0.kind');
});

it('the booking diagram receives the space constraints', function () {
    $this->space->update([
        'constraints_json' => [
            ['id' => 'c1', 'kind' => 'wall', 'x' => 100, 'y' => 100, 'width_ft' => 10, 'height_ft' => 0.5, 'rotation' => 0],
            ['id' => 'c2', 'kind' => 'column', 'x' => 200, 'y' => 200, 'width_ft' => 2, 'height_ft' => 2],
        ],
    ]);

    $booking = Booking::factory()->create();
    $booking->spaces()->create([
        'space_id' => $this->space->id,
        'start_at' => $booking->start_at,
        'end_at' => $booking->end_at,
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}/diagram?space_id={$this->space->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/diagram')
            ->has('space.constraints', 2)
            ->where('space.constraints.0.kind', 'wall'));
});

it('seeds the expected feature counts onto each recognized space', function () {
    Space::factory()->create(['name' => 'Coral Reef Grand Ballroom + Exhibit Hall A']);
    Space::factory()->create(['name' => 'Main Theater']);
    Space::factory()->create(['name' => 'Sentinel Bay Arena']);
    Space::factory()->create(['name' => 'Indoor Expo Hall']);

    $this->seed(SpaceConstraintsSeeder::class);

    $countsByName = [
        'Coral Reef Grand Ballroom + Exhibit Hall A' => 12,
        'Main Theater' => 5,
        'Sentinel Bay Arena' => 3,
        'Indoor Expo Hall' => 7,
    ];

    foreach ($countsByName as $name => $expected) {
        $space = Space::query()->where('name', $name)->firstOrFail();
        expect(count($space->fresh()->constraints_json))
            ->toBe($expected, "expected {$expected} constraints on {$name}");
    }
});

it('the seeder is idempotent on a space that already has constraints', function () {
    $existing = [
        ['id' => 'manual', 'kind' => 'wall', 'x' => 0, 'y' => 0, 'width_ft' => 10, 'height_ft' => 0.5, 'rotation' => 0],
    ];
    Space::factory()->create([
        'name' => 'Coral Reef Grand Ballroom + Exhibit Hall A',
        'constraints_json' => $existing,
    ]);

    $this->seed(SpaceConstraintsSeeder::class);

    $space = Space::query()->where('name', 'Coral Reef Grand Ballroom + Exhibit Hall A')->firstOrFail();
    expect($space->constraints_json)->toBe($existing);
});
