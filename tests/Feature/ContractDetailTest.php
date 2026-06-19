<?php

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ContractSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the contract show page with booking and signers', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create(['name' => 'Spring Symposium']);
    $contract = Contract::factory()->create([
        'booking_id' => $booking->id,
        'status' => ContractStatus::Sent->value,
        'rendered_html' => '<h1>Contract body</h1><p>Terms...</p>',
        'sent_at' => now()->subDays(2),
    ]);
    ContractSigner::factory()->create([
        'contract_id' => $contract->id,
        'name' => 'Pat Client',
        'email' => 'pat@example.test',
        'signing_order' => 1,
        'viewed_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get("/contracts/{$contract->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contracts/show')
            ->where('contract.id', $contract->id)
            ->where('contract.reference', $contract->reference)
            ->where('contract.status', 'sent')
            ->where('contract.booking.name', 'Spring Symposium')
            ->has('contract.signers', 1)
            ->where('contract.signers.0.name', 'Pat Client')
        );
});

it('renders when rendered_html is null', function () {
    $user = grantSuperAdmin();
    $contract = Contract::factory()->create([
        'rendered_html' => null,
    ]);

    $this->actingAs($user)
        ->get("/contracts/{$contract->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('contract.rendered_html', null)
        );
});

it('returns 404 for an unknown contract id', function () {
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/contracts/999999')
        ->assertNotFound();
});

it('includes parent + addenda when the contract is part of a chain', function () {
    $user = grantSuperAdmin();
    $parent = Contract::factory()->create(['kind' => 'contract']);
    $addendum = Contract::factory()->create([
        'parent_contract_id' => $parent->id,
        'kind' => 'addendum',
    ]);

    $this->actingAs($user)
        ->get("/contracts/{$parent->id}")
        ->assertInertia(fn ($page) => $page
            ->has('contract.addenda', 1)
            ->where('contract.addenda.0.id', $addendum->id)
        );

    $this->actingAs($user)
        ->get("/contracts/{$addendum->id}")
        ->assertInertia(fn ($page) => $page
            ->where('contract.parent.id', $parent->id)
            ->where('contract.parent.kind', 'contract')
        );
});
