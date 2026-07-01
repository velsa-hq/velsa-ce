<?php

use App\Services\Signing\FakeSignatureProvider;
use App\Services\Signing\SignatureProvider;
use App\Support\SafeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('reads safe-mode flags from config', function () {
    config([
        'velsa.safe_mode' => true,
        'velsa.safe_mode_label' => 'TRAINING',
        'velsa.safe_mode_mail_to' => 'trainer@example.gov',
    ]);

    expect(SafeMode::enabled())->toBeTrue()
        ->and(SafeMode::label())->toBe('TRAINING')
        ->and(SafeMode::mailRecipient())->toBe('trainer@example.gov');
});

it('forces the fake signature provider in safe mode', function () {
    config(['velsa.safe_mode' => true]);
    app()->forgetInstance(SignatureProvider::class);

    expect(app(SignatureProvider::class))->toBeInstanceOf(FakeSignatureProvider::class);
});

it('suppresses outbound mail to the log channel when no sink is set', function () {
    config(['velsa.safe_mode' => true, 'velsa.safe_mode_mail_to' => null, 'mail.default' => 'smtp']);

    SafeMode::applyMail();

    expect(config('mail.default'))->toBe('log');
});

it('redirects (not suppresses) when a sink address is configured', function () {
    config(['velsa.safe_mode' => true, 'velsa.safe_mode_mail_to' => 'sink@example.gov', 'mail.default' => 'smtp']);

    SafeMode::applyMail();

    // left on the real mailer; Mail::alwaysTo handles the redirect
    expect(config('mail.default'))->toBe('smtp');
});

it('does nothing to mail when safe mode is off', function () {
    config(['velsa.safe_mode' => false, 'mail.default' => 'smtp']);

    SafeMode::applyMail();

    expect(config('mail.default'))->toBe('smtp');
});

it('shares the safe-mode banner flag to the front end', function () {
    config(['velsa.safe_mode' => true, 'velsa.safe_mode_label' => 'DEMO']);
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn (Assert $page) => $page->where('safeMode.label', 'DEMO'),
    );
});

it('shares no safe-mode flag on a normal instance', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->get('/dashboard')->assertInertia(
        fn (Assert $page) => $page->where('safeMode', null),
    );
});
