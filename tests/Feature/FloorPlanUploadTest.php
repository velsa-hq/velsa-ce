<?php

use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

it('uploads a floor-plan image to a space and exposes its URL', function () {
    $admin = grantSuperAdmin();
    $space = Space::factory()->create();

    $this->actingAs($admin)
        ->post("/spaces/{$space->id}/floorplan", ['floorplan' => UploadedFile::fake()->image('plan.png', 1200, 800)])
        ->assertRedirect();

    expect($space->fresh()->getMedia('floorplan'))->toHaveCount(1)
        ->and($space->fresh()->floorPlanUrl())->not->toBeNull();
});

it('replaces the floor plan on re-upload (single file) and can remove it', function () {
    $admin = grantSuperAdmin();
    $space = Space::factory()->create();

    $this->actingAs($admin)->post("/spaces/{$space->id}/floorplan", ['floorplan' => UploadedFile::fake()->image('a.png')]);
    $this->actingAs($admin)->post("/spaces/{$space->id}/floorplan", ['floorplan' => UploadedFile::fake()->image('b.png')]);
    expect($space->fresh()->getMedia('floorplan'))->toHaveCount(1);

    $this->actingAs($admin)->delete("/spaces/{$space->id}/floorplan")->assertRedirect();
    expect($space->fresh()->getMedia('floorplan'))->toHaveCount(0);
});

it('rejects a non-image upload', function () {
    $admin = grantSuperAdmin();
    $space = Space::factory()->create();

    $this->actingAs($admin)
        ->post("/spaces/{$space->id}/floorplan", ['floorplan' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
        ->assertSessionHasErrors('floorplan');
});

it('forbids a user without space management from uploading', function () {
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);
    $user = User::factory()->create();
    $user->assignRoleAt($venue, 'event_coordinator'); // spaces.view, not spaces.manage

    $this->actingAs($user)
        ->post("/spaces/{$space->id}/floorplan", ['floorplan' => UploadedFile::fake()->image('x.png')])
        ->assertForbidden();
});
