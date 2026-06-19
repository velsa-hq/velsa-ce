<?php

use App\Enums\InsuranceCertificateStatus;
use App\Mail\CertificatesExpiringDigest;
use App\Models\InsuranceCertificate;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('auto-expires approved certificates past their expiry date', function () {
    Mail::fake();
    $lapsed = InsuranceCertificate::factory()->approved()
        ->expiringOn(now()->subDay()->toDateString())->create();
    $current = InsuranceCertificate::factory()->approved()
        ->expiringOn(now()->addMonths(6)->toDateString())->create();

    $this->artisan('compliance:check-certificates')->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(InsuranceCertificateStatus::Expired)
        ->and($current->fresh()->status)->toBe(InsuranceCertificateStatus::Approved);
});

it('queues an expiry digest when reminders are enabled', function () {
    Mail::fake();
    app(SystemSettings::class)->set('compliance.expiry_reminders_enabled', true);
    app(SystemSettings::class)->set('compliance.notification_recipients', 'risk@venue.gov');
    app(SystemSettings::class)->set('compliance.expiry_reminder_days', 30);

    InsuranceCertificate::factory()->approved()
        ->expiringOn(now()->addDays(10)->toDateString())->create();

    $this->artisan('compliance:check-certificates')->assertSuccessful();

    Mail::assertQueued(CertificatesExpiringDigest::class, fn ($m) => $m->hasTo('risk@venue.gov'));
});

it('does not email when reminders are disabled', function () {
    Mail::fake();
    app(SystemSettings::class)->set('compliance.notification_recipients', 'risk@venue.gov');

    InsuranceCertificate::factory()->approved()
        ->expiringOn(now()->addDays(10)->toDateString())->create();

    $this->artisan('compliance:check-certificates')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('does not email when nothing is expiring soon', function () {
    Mail::fake();
    app(SystemSettings::class)->set('compliance.expiry_reminders_enabled', true);
    app(SystemSettings::class)->set('compliance.notification_recipients', 'risk@venue.gov');

    InsuranceCertificate::factory()->approved()
        ->expiringOn(now()->addMonths(6)->toDateString())->create();

    $this->artisan('compliance:check-certificates')->assertSuccessful();

    Mail::assertNothingQueued();
});
