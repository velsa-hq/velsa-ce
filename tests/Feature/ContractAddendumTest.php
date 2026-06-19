<?php

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use App\Services\Signing\ContractDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->booking = Booking::factory()->create();
});

function signedContract(Booking $booking): Contract
{
    return Contract::query()->create([
        'booking_id' => $booking->id,
        'kind' => 'contract',
        'status' => ContractStatus::Signed->value,
        'total_cents' => $booking->total_cents,
        'rendered_html' => '<h1>Parent contract body</h1>',
        'signed_at' => now()->subDays(3),
    ]);
}

it('drafts an addendum on a signed parent contract', function () {
    $parent = signedContract($this->booking);

    $this->actingAs($this->user)
        ->post("/contracts/{$parent->id}/addenda", [
            'reason' => 'Date shifted to October 15',
        ])
        ->assertRedirect();

    $addendum = Contract::query()->where('parent_contract_id', $parent->id)->firstOrFail();
    expect($addendum->kind)->toBe('addendum')
        ->and($addendum->status)->toBe(ContractStatus::Draft)
        ->and($addendum->reference)->toStartWith('AD-')
        ->and($addendum->booking_id)->toBe($parent->booking_id)
        ->and($addendum->rendered_html)->toContain('Date shifted to October 15');
});

it('also accepts a PartiallySigned parent', function () {
    $parent = signedContract($this->booking);
    $parent->update(['status' => ContractStatus::PartiallySigned->value]);

    app(ContractDispatcher::class)->draftAddendum($parent->fresh(), 'partial OK');

    expect(Contract::query()->where('parent_contract_id', $parent->id)->exists())->toBeTrue();
});

it('refuses to draft an addendum on a draft parent', function () {
    $parent = Contract::query()->create([
        'booking_id' => $this->booking->id,
        'kind' => 'contract',
        'status' => ContractStatus::Draft->value,
        'total_cents' => 0,
    ]);

    expect(fn () => app(ContractDispatcher::class)->draftAddendum($parent, 'too early'))
        ->toThrow(RuntimeException::class, 'Addenda may only be drafted on a signed parent contract.');

    $this->actingAs($this->user)
        ->post("/contracts/{$parent->id}/addenda", ['reason' => 'too early'])
        ->assertSessionHasErrors('addendum');

    expect(Contract::query()->where('parent_contract_id', $parent->id)->exists())->toBeFalse();
});

it('refuses to draft an addendum on a declined or expired parent', function () {
    foreach ([ContractStatus::Declined, ContractStatus::Expired, ContractStatus::Sent, ContractStatus::Viewed] as $status) {
        $parent = Contract::query()->create([
            'booking_id' => $this->booking->id,
            'kind' => 'contract',
            'status' => $status->value,
            'total_cents' => 0,
        ]);

        expect(fn () => app(ContractDispatcher::class)->draftAddendum($parent, 'nope'))
            ->toThrow(RuntimeException::class);
    }
});

it('keeps a signed parent immutable after an addendum exists', function () {
    $parent = signedContract($this->booking);
    app(ContractDispatcher::class)->draftAddendum($parent, 'something changed');

    expect(fn () => $parent->update(['rendered_html' => '<h1>Tampered</h1>']))
        ->toThrow(RuntimeException::class, 'immutable');

    expect(fn () => $parent->update(['total_cents' => 999]))
        ->toThrow(RuntimeException::class, 'immutable');
});

it('addendum inherits the same immutability rule once signed', function () {
    $parent = signedContract($this->booking);
    $addendum = app(ContractDispatcher::class)->draftAddendum($parent, 'first round');

    // draft addenda are editable
    $addendum->update(['rendered_html' => '<h1>Working draft</h1>']);
    expect($addendum->fresh()->rendered_html)->toContain('Working draft');

    // signing locks it under the same rule
    $addendum->update(['status' => ContractStatus::Signed->value, 'signed_at' => now()]);

    expect(fn () => $addendum->fresh()->update(['rendered_html' => '<h1>Forbidden</h1>']))
        ->toThrow(RuntimeException::class, 'immutable');
});

it('the contract show page exposes addenda + parent', function () {
    $parent = signedContract($this->booking);
    app(ContractDispatcher::class)->draftAddendum($parent, 'demo addendum');

    $this->actingAs($this->user)
        ->get("/contracts/{$parent->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contracts/show')
            ->has('contract.addenda', 1)
            ->where('contract.addenda.0.kind', 'addendum'));
});

it('multiple addenda are allowed on the same parent', function () {
    $parent = signedContract($this->booking);

    app(ContractDispatcher::class)->draftAddendum($parent, 'first');
    app(ContractDispatcher::class)->draftAddendum($parent, 'second');
    app(ContractDispatcher::class)->draftAddendum($parent, 'third');

    expect(Contract::query()->where('parent_contract_id', $parent->id)->count())->toBe(3);
});
