<?php

use App\Enums\ExhibitorPermitStatus;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorPermit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
});

it('shows the exhibitor only their own permit requests', function () {
    ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->create();
    $other = Exhibitor::factory()->create(['exhibitor_event_id' => $this->exhibitor->exhibitor_event_id]);
    ExhibitorPermit::factory()->forExhibitor($other)->create();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->get('/portal/permits')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('portal/permits')
            ->has('permits', 1)
        );
});

it('lets an exhibitor submit a pending permit request with an optional document', function () {
    Storage::fake('local');

    $this->actingAs($this->exhibitor, 'exhibitor')->post('/portal/permits', [
        'permit_type' => 'food_sampling',
        'details' => 'Sampling cold brew, 2oz cups.',
        'document' => UploadedFile::fake()->image('product-list.png', 800, 600),
    ])->assertRedirect();

    $permit = ExhibitorPermit::sole();
    expect($permit->exhibitor_id)->toBe($this->exhibitor->id)
        ->and($permit->status)->toBe(ExhibitorPermitStatus::Pending)
        ->and($permit->submitted_via_portal)->toBeTrue()
        ->and($permit->getFirstMedia('document'))->not->toBeNull();
});

it('allows a permit request without a document', function () {
    $this->actingAs($this->exhibitor, 'exhibitor')->post('/portal/permits', [
        'permit_type' => 'amplified_sound',
        'details' => 'Looping product video at booth volume.',
    ])->assertRedirect();

    expect(ExhibitorPermit::sole()->getFirstMedia('document'))->toBeNull();
});

it('requires a permit type and details', function () {
    $this->actingAs($this->exhibitor, 'exhibitor')
        ->from('/portal/permits')
        ->post('/portal/permits', [])
        ->assertSessionHasErrors(['permit_type', 'details']);
});

it('lets an exhibitor withdraw their own pending request', function () {
    $permit = ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->create();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/permits/{$permit->id}/cancel")
        ->assertRedirect();

    expect($permit->refresh()->status)->toBe(ExhibitorPermitStatus::Cancelled);
});

it('will not let an exhibitor cancel another exhibitor request', function () {
    $other = Exhibitor::factory()->create(['exhibitor_event_id' => $this->exhibitor->exhibitor_event_id]);
    $permit = ExhibitorPermit::factory()->forExhibitor($other)->create();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/permits/{$permit->id}/cancel")
        ->assertNotFound();

    expect($permit->refresh()->status)->toBe(ExhibitorPermitStatus::Pending);
});

it('will not cancel a request that is no longer pending', function () {
    $permit = ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->approved()->create();

    $this->actingAs($this->exhibitor, 'exhibitor')
        ->post("/portal/permits/{$permit->id}/cancel")
        ->assertStatus(422);
});

it('blocks the permit portal without an exhibitor session', function () {
    $this->get('/portal/permits')->assertRedirect();
});

it('lists permits with a pending count for a compliance admin', function () {
    ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->count(2)->create();
    ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->approved()->create();

    $this->actingAs(grantSuperAdmin())
        ->get('/admin/exhibitor-permits')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/exhibitor-permits/index')
            ->has('permits.data', 3)
            ->where('counts.pending', 2)
        );
});

it('approves a permit and records the reviewer', function () {
    $admin = grantSuperAdmin();
    $permit = ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->create();

    $this->actingAs($admin)->put("/admin/exhibitor-permits/{$permit->id}", [
        'status' => 'approved',
        'review_notes' => 'Floor mats down before move-in.',
    ])->assertRedirect();

    $permit->refresh();
    expect($permit->status)->toBe(ExhibitorPermitStatus::Approved)
        ->and($permit->review_notes)->toBe('Floor mats down before move-in.')
        ->and($permit->reviewed_by)->toBe($admin->id)
        ->and($permit->reviewed_at)->not->toBeNull();
});

it('denies a permit', function () {
    $permit = ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->create();

    $this->actingAs(grantSuperAdmin())->put("/admin/exhibitor-permits/{$permit->id}", [
        'status' => 'denied',
    ])->assertRedirect();

    expect($permit->refresh()->status)->toBe(ExhibitorPermitStatus::Denied);
});

it('rejects an invalid review status', function () {
    $permit = ExhibitorPermit::factory()->forExhibitor($this->exhibitor)->create();

    $this->actingAs(grantSuperAdmin())
        ->from('/admin/exhibitor-permits')
        ->put("/admin/exhibitor-permits/{$permit->id}", ['status' => 'cancelled'])
        ->assertSessionHasErrors('status');

    expect($permit->refresh()->status)->toBe(ExhibitorPermitStatus::Pending);
});

it('forbids a non-compliance user from the permit queue', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/exhibitor-permits')
        ->assertForbidden();
});
