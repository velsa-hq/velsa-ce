<?php

use App\Enums\InsuranceCertificateStatus;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\InsuranceCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
});

it('shows the exhibitor only their own certificates', function () {
    InsuranceCertificate::factory()->forExhibitor($this->exhibitor)->create();
    $other = Exhibitor::factory()->create(['exhibitor_event_id' => $this->exhibitor->exhibitor_event_id]);
    InsuranceCertificate::factory()->forExhibitor($other)->create();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->get('/portal/insurance')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('portal/insurance')
            ->has('certificates', 1)
        );
});

it('lets an exhibitor upload a certificate as pending via the portal', function () {
    Storage::fake('local');

    $this->actingAs($this->exhibitor, 'exhibitor')->post('/portal/insurance', [
        'policy_type' => 'general_liability',
        'carrier' => 'Acme Mutual',
        'expires_on' => now()->addYear()->toDateString(),
        'document' => UploadedFile::fake()->image('coi.png', 800, 600),
    ])->assertRedirect();

    $cert = InsuranceCertificate::sole();
    expect($cert->holder_type)->toBe(Exhibitor::class)
        ->and($cert->holder_id)->toBe($this->exhibitor->id)
        ->and($cert->status)->toBe(InsuranceCertificateStatus::Pending)
        ->and($cert->submitted_via_portal)->toBeTrue()
        ->and($cert->getFirstMedia('certificate'))->not->toBeNull();
});

it('requires a document on portal upload', function () {
    $this->actingAs($this->exhibitor, 'exhibitor')
        ->from('/portal/insurance')
        ->post('/portal/insurance', [
            'policy_type' => 'general_liability',
            'expires_on' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('document');
});

it('blocks insurance upload without an exhibitor session', function () {
    $this->post('/portal/insurance', [])->assertRedirect();
});
