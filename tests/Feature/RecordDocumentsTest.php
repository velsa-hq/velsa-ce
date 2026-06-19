<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('attaches a document to a client and lists it on the show page', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $client = Client::factory()->create();

    $this->actingAs($admin)->post("/clients/{$client->id}/documents", [
        'file' => UploadedFile::fake()->image('rfp.png', 600, 400),
        'title' => 'Client RFP',
    ])->assertRedirect();

    expect($client->fresh()->getMedia('documents'))->toHaveCount(1);

    $this->actingAs($admin)
        ->get("/clients/{$client->id}")
        ->assertInertia(fn (Assert $p) => $p->has('documents', 1)->where('documents.0.name', 'Client RFP'));
});

it('removes a client document', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $client = Client::factory()->create();
    $client->addMedia(UploadedFile::fake()->image('x.png'))->toMediaCollection('documents');
    $media = $client->getFirstMedia('documents');

    $this->actingAs($admin)
        ->delete("/clients/{$client->id}/documents/{$media->id}")
        ->assertRedirect();

    expect($client->fresh()->getMedia('documents'))->toHaveCount(0);
});

it('attaches a document to a booking', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $booking = Booking::factory()->create();

    $this->actingAs($admin)->post("/bookings/{$booking->id}/documents", [
        'file' => UploadedFile::fake()->image('floorplan.png'),
    ])->assertRedirect();

    expect($booking->fresh()->getMedia('documents'))->toHaveCount(1);
});

it('will not delete a document that belongs to a different record', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $a = Client::factory()->create();
    $b = Client::factory()->create();
    $a->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection('documents');
    $mediaOfA = $a->getFirstMedia('documents');

    // delete A's media via B's route -> 404, A keeps its document
    $this->actingAs($admin)
        ->delete("/clients/{$b->id}/documents/{$mediaOfA->id}")
        ->assertNotFound();

    expect($a->fresh()->getMedia('documents'))->toHaveCount(1);
});

it('forbids a user without clients.manage from attaching client documents', function () {
    Storage::fake('local');
    $venue = Venue::factory()->create();
    $user = User::factory()->create();
    test()->seed(RolesAndPermissionsSeeder::class);
    $user->assignRoleAt($venue, 'event_coordinator'); // has bookings.edit, not clients.manage
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post("/clients/{$client->id}/documents", ['file' => UploadedFile::fake()->image('x.png')])
        ->assertForbidden();
});
