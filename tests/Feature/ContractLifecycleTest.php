<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
});

// delete / restore / archive

it('soft-deletes a draft and hides it from the index', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Draft)->create();

    $this->actingAs($this->user)
        ->delete("/contracts/{$contract->id}")
        ->assertRedirect('/contracts');

    expect($contract->fresh()->trashed())->toBeTrue()
        ->and(Contract::query()->where('id', $contract->id)->exists())->toBeFalse()
        ->and(Contract::withTrashed()->where('id', $contract->id)->exists())->toBeTrue();
});

it('refuses to delete a sent or signed contract', function () {
    $sent = Contract::factory()->inStatus(ContractStatus::Sent)->create();
    $signed = Contract::factory()->inStatus(ContractStatus::Signed)->create();

    $this->actingAs($this->user)->delete("/contracts/{$sent->id}")->assertStatus(422);
    $this->actingAs($this->user)->delete("/contracts/{$signed->id}")->assertStatus(422);

    expect($sent->fresh()->trashed())->toBeFalse()
        ->and($signed->fresh()->trashed())->toBeFalse();
});

it('lists deleted contracts in the archive and restores them', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Draft)->create();
    $contract->delete();

    $this->actingAs($this->user)
        ->get('/contracts/archive')
        ->assertInertia(fn ($page) => $page
            ->component('contracts/archive')
            ->where('contracts.data.0.reference', $contract->reference));

    $this->actingAs($this->user)
        ->patch("/contracts/{$contract->id}/restore")
        ->assertRedirect();

    expect($contract->fresh()->trashed())->toBeFalse();
});

// void

it('voids an in-flight contract', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create();

    $this->actingAs($this->user)
        ->post("/contracts/{$contract->id}/void", ['reason' => 'client backed out'])
        ->assertRedirect();

    $fresh = $contract->fresh();
    expect($fresh->status)->toBe(ContractStatus::Voided)
        ->and($fresh->voided_at)->not->toBeNull();
});

it('refuses to void a draft or a signed contract', function () {
    $draft = Contract::factory()->inStatus(ContractStatus::Draft)->create();
    $signed = Contract::factory()->inStatus(ContractStatus::Signed)->create();

    $this->actingAs($this->user)->post("/contracts/{$draft->id}/void")->assertSessionHasErrors('void');
    $this->actingAs($this->user)->post("/contracts/{$signed->id}/void")->assertSessionHasErrors('void');

    expect($draft->fresh()->status)->toBe(ContractStatus::Draft)
        ->and($signed->fresh()->status)->toBe(ContractStatus::Signed);
});

// expiry

it('expires stale sent contracts but keeps recent ones', function () {
    app(SystemSettings::class)->set('defaults.contract_expiry_after_days', 30);

    $stale = Contract::factory()->inStatus(ContractStatus::Sent)->create(['sent_at' => now()->subDays(45)]);
    $recent = Contract::factory()->inStatus(ContractStatus::Sent)->create(['sent_at' => now()->subDays(5)]);
    $signed = Contract::factory()->inStatus(ContractStatus::Signed)->create(['sent_at' => now()->subDays(90)]);

    $this->artisan('contracts:expire-stale')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(ContractStatus::Expired)
        ->and($recent->fresh()->status)->toBe(ContractStatus::Sent)
        ->and($signed->fresh()->status)->toBe(ContractStatus::Signed);
});

// multi-signer send

it('sends a contract to multiple signers in signing order', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Draft)->create();

    $this->actingAs($this->user)->post("/contracts/{$contract->id}/send", [
        'signers' => [
            ['name' => 'Client A', 'email' => 'a@x.test', 'role' => 'client', 'signing_order' => 1],
            ['name' => 'Venue Rep', 'email' => 'b@x.test', 'role' => 'venue', 'signing_order' => 2],
        ],
    ])->assertRedirect();

    $fresh = $contract->fresh();
    expect($fresh->status)->toBe(ContractStatus::Sent)
        ->and($fresh->signers()->count())->toBe(2)
        ->and($fresh->signers()->orderBy('signing_order')->pluck('email')->all())
        ->toBe(['a@x.test', 'b@x.test']);
});

// transition guard

it('does not resurrect a voided contract via a late signed event', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Voided)->create();
    $signer = ContractSigner::factory()->create(['contract_id' => $contract->id]);

    $contract->markSignedBy($signer);

    expect($contract->fresh()->status)->toBe(ContractStatus::Voided);
});

it('filters the contracts index by status', function () {
    Contract::factory()->inStatus(ContractStatus::Sent)->create();
    Contract::factory()->inStatus(ContractStatus::Draft)->create();

    $this->actingAs($this->user)
        ->get('/contracts?status=sent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contracts/index')
            ->has('contracts.data', 1)
            ->where('contracts.data.0.status', 'sent'));
});
