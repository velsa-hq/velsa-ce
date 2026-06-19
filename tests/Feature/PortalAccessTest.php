<?php

use App\Mail\ExhibitorPortalLink;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->event = ExhibitorEvent::factory()->create();
});

it('renders the public access-request page', function () {
    $this->get('/portal/access')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('portal/access'));
});

it('emails a magic link to a registered exhibitor', function () {
    Mail::fake();
    Exhibitor::factory()->for($this->event, 'event')->create(['email' => 'vendor@acme.test']);

    $this->post('/portal/access', ['email' => 'vendor@acme.test'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    Mail::assertSent(ExhibitorPortalLink::class, fn ($mail) => $mail->hasTo('vendor@acme.test'));
});

it('matches the exhibitor email case-insensitively and stores it lowercase', function () {
    Mail::fake();
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create(['email' => 'Vendor@Acme.TEST']);

    expect($exhibitor->fresh()->email)->toBe('vendor@acme.test');

    // a differently-cased request still finds the exhibitor
    $this->post('/portal/access', ['email' => 'VENDOR@acme.test'])->assertRedirect();

    Mail::assertSent(ExhibitorPortalLink::class, fn ($mail) => $mail->hasTo('vendor@acme.test'));
});

it('honors the configurable magic-link lifetime setting', function () {
    app(SystemSettings::class)->set('security.portal_magic_link_ttl_days', '14');
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create(['email' => 'vendor@acme.test']);

    $this->post('/portal/access', ['email' => 'vendor@acme.test'])->assertRedirect();

    $expiry = $exhibitor->refresh()->magic_token_expires_at;
    // 14-day window, minute of slack for execution time
    expect($expiry->between(now()->addDays(14)->subMinute(), now()->addDays(14)->addMinute()))->toBeTrue();
});

it('returns the same response for an unknown email and sends nothing', function () {
    Mail::fake();

    $this->post('/portal/access', ['email' => 'nobody@nowhere.test'])
        ->assertRedirect()
        ->assertSessionHas('toast.type', 'success');

    Mail::assertNothingSent();
});

it('validates the email', function () {
    $this->post('/portal/access', ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
});
