<?php

use App\Enums\InsuranceCertificateStatus;
use App\Models\Client;
use App\Models\InsuranceCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists certificates with counts', function () {
    $admin = grantSuperAdmin();
    InsuranceCertificate::factory()->count(2)->create();
    InsuranceCertificate::factory()->approved()->expiringOn(now()->addDays(10)->toDateString())->create();

    $this->actingAs($admin)
        ->get('/admin/insurance-certificates')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/insurance-certificates/index')
            ->has('certificates.data', 3)
            ->where('counts.pending', 2)
            ->where('counts.expiring', 1)
        );
});

it('records a staff-uploaded certificate as pending', function () {
    Storage::fake('local');
    $admin = grantSuperAdmin();
    $client = Client::factory()->create();

    $this->actingAs($admin)->post('/admin/insurance-certificates', [
        'holder_kind' => 'client',
        'holder_id' => $client->id,
        'policy_type' => 'general_liability',
        'carrier' => 'Acme Mutual',
        'coverage_amount' => '1000000',
        'expires_on' => now()->addYear()->toDateString(),
        'document' => UploadedFile::fake()->image('coi.png', 800, 600),
    ])->assertRedirect();

    $cert = InsuranceCertificate::sole();
    expect($cert->holder_id)->toBe($client->id)
        ->and($cert->holder_type)->toBe(Client::class)
        ->and($cert->status)->toBe(InsuranceCertificateStatus::Pending)
        ->and($cert->coverage_amount_cents)->toBe(100_000_000)
        ->and($cert->getFirstMedia('certificate'))->not->toBeNull()
        ->and($cert->submitted_via_portal)->toBeFalse();
});

it('approves a certificate and records the reviewer', function () {
    $admin = grantSuperAdmin();
    $cert = InsuranceCertificate::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/insurance-certificates/{$cert->id}", ['status' => 'approved'])
        ->assertRedirect();

    $cert->refresh();
    expect($cert->status)->toBe(InsuranceCertificateStatus::Approved)
        ->and($cert->reviewed_by)->toBe($admin->id)
        ->and($cert->reviewed_at)->not->toBeNull();
});

it('rejects a certificate with a note', function () {
    $admin = grantSuperAdmin();
    $cert = InsuranceCertificate::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/insurance-certificates/{$cert->id}", [
            'status' => 'rejected',
            'review_notes' => 'Coverage too low.',
        ])
        ->assertRedirect();

    $cert->refresh();
    expect($cert->status)->toBe(InsuranceCertificateStatus::Rejected)
        ->and($cert->review_notes)->toBe('Coverage too low.');
});

it('rejects an invalid review status', function () {
    $admin = grantSuperAdmin();
    $cert = InsuranceCertificate::factory()->create();

    $this->actingAs($admin)
        ->from('/admin/insurance-certificates')
        ->put("/admin/insurance-certificates/{$cert->id}", ['status' => 'expired'])
        ->assertSessionHasErrors('status');
});

it('deletes a certificate', function () {
    $admin = grantSuperAdmin();
    $cert = InsuranceCertificate::factory()->create();

    $this->actingAs($admin)
        ->delete("/admin/insurance-certificates/{$cert->id}")
        ->assertRedirect();

    expect(InsuranceCertificate::find($cert->id))->toBeNull();
});

it('forbids a non-compliance user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/insurance-certificates')->assertForbidden();
});
