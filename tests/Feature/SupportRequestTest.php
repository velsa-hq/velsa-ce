<?php

use App\Enums\SupportRequestStatus;
use App\Mail\SupportRequestSubmitted;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the support form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/support')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('support/create')
            ->has('categories')
        );
});

it('records a support request with captured context', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'category' => 'problem',
        'subject' => 'Cannot export',
        'body' => 'The CSV export button does nothing.',
        'page_url' => '/reports/ar-aging',
    ])->assertRedirect();

    $request = SupportRequest::sole();
    expect($request->user_id)->toBe($user->id);
    expect($request->category->value)->toBe('problem');
    expect($request->subject)->toBe('Cannot export');
    expect($request->page_url)->toBe('/reports/ar-aging');
    expect($request->status)->toBe(SupportRequestStatus::Open);
    expect($request->app_version)->toBe(config('app.version'));
});

it('emails recipients when notifications are enabled', function () {
    Mail::fake();
    app(SystemSettings::class)->set('support.notifications_enabled', true);
    app(SystemSettings::class)->set('support.recipients', 'ops@example.com, lead@example.com');

    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'category' => 'question',
        'subject' => 'How do I?',
        'body' => 'A question.',
    ])->assertRedirect();

    Mail::assertQueued(SupportRequestSubmitted::class, fn ($mail) => $mail->hasTo('ops@example.com'));
});

it('does not email when notifications are disabled', function () {
    Mail::fake();
    app(SystemSettings::class)->set('support.recipients', 'ops@example.com');
    // notifications_enabled left at default (false)

    $user = User::factory()->create();

    $this->actingAs($user)->post('/support', [
        'category' => 'question',
        'subject' => 'Hi',
        'body' => 'Body.',
    ])->assertRedirect();

    Mail::assertNothingQueued();
    expect(SupportRequest::count())->toBe(1);
});

it('validates the request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/support')
        ->post('/support', ['category' => 'nonsense', 'subject' => '', 'body' => ''])
        ->assertRedirect('/support')
        ->assertSessionHasErrors(['category', 'subject', 'body']);
});

it('requires authentication', function () {
    $this->post('/support', [])->assertRedirect('/login');
});
