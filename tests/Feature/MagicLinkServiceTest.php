<?php

use App\Models\Exhibitor;
use App\Services\MagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(MagicLinkService::class);
    $this->exhibitor = Exhibitor::factory()->create();
});

it('issues a token, stores its hash, and returns the plaintext', function () {
    $plain = $this->service->issue($this->exhibitor);

    expect($plain)->toBeString()->toHaveLength(64);

    $this->exhibitor->refresh();
    expect($this->exhibitor->magic_token)->not->toBeNull()
        ->and(Hash::check($plain, $this->exhibitor->magic_token))->toBeTrue()
        ->and($this->exhibitor->magic_token_expires_at)->not->toBeNull();
});

it('does not store the plaintext token in the database', function () {
    $plain = $this->service->issue($this->exhibitor);

    expect($this->exhibitor->fresh()->magic_token)->not->toBe($plain);
});

it('verifies a valid token and returns the matching exhibitor', function () {
    $plain = $this->service->issue($this->exhibitor);

    $resolved = $this->service->verify($plain);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->id)->toBe($this->exhibitor->id);
});

it('returns null when the token does not match anything', function () {
    $this->service->issue($this->exhibitor);

    expect($this->service->verify('not-a-real-token'))->toBeNull();
});

it('defaults the validity window to 3 days or less', function () {
    expect(MagicLinkService::DEFAULT_TTL_DAYS)->toBeLessThanOrEqual(3);

    $this->freezeTime();
    $this->service->issue($this->exhibitor);

    expect($this->exhibitor->fresh()->magic_token_expires_at->timestamp)
        ->toBe(now()->addDays(MagicLinkService::DEFAULT_TTL_DAYS)->timestamp);
});

it('returns null when the token is past its expiry', function () {
    $plain = $this->service->issue($this->exhibitor, ttlDays: 1);

    $this->exhibitor->forceFill([
        'magic_token_expires_at' => now()->subHour(),
    ])->save();

    expect($this->service->verify($plain))->toBeNull();
});

it('consume() clears the token and expiry', function () {
    $plain = $this->service->issue($this->exhibitor);

    $this->service->consume($this->exhibitor);

    $this->exhibitor->refresh();
    expect($this->exhibitor->magic_token)->toBeNull()
        ->and($this->exhibitor->magic_token_expires_at)->toBeNull();
});

it('builds a login URL that includes the plaintext token', function () {
    $url = $this->service->loginUrl('abc123');

    expect($url)->toBe('/portal/login/abc123');
});
