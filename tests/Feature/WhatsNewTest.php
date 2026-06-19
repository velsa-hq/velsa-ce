<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the release-notes feed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/whats-new')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('whats-new/index')
            ->has('releases')
        );
});

it('stamps whats_new_seen_at when the feed is opened', function () {
    $user = User::factory()->create(['whats_new_seen_at' => null]);

    expect($user->whats_new_seen_at)->toBeNull();

    $this->actingAs($user)->get('/whats-new')->assertOk();

    expect($user->fresh()->whats_new_seen_at)->not->toBeNull();
});

it('marks updates unread for a user who has never opened the feed', function () {
    $user = User::factory()->create(['whats_new_seen_at' => null]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->where('whatsNew.unread', true));
});

it('clears the unread indicator after the feed is opened', function () {
    $user = User::factory()->create(['whats_new_seen_at' => null]);

    $this->actingAs($user)->get('/whats-new')->assertOk();

    $this->actingAs($user->fresh())
        ->get('/dashboard')
        ->assertInertia(fn (Assert $p) => $p->where('whatsNew.unread', false));
});

it('requires authentication', function () {
    $this->get('/whats-new')->assertRedirect('/login');
});
