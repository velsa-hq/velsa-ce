<?php

use App\Enums\BookingNarrativeKind;
use App\Enums\BookingStatus;
use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\BookingNarrative;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $this->user = grantSuperAdmin();
    $this->actingAs($this->user);

    $client = Client::factory()->create();
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'email' => 'primary@client.test',
    ]);

    $this->booking = Booking::factory()->withStatus(BookingStatus::Hold)->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
});

// ---------- Booking status changes ----------

it('appends a system narrative when a booking status changes', function () {
    $this->booking->update(['status' => BookingStatus::Tentative->value]);

    $narrative = $this->booking->narratives()->latest('id')->first();
    expect($narrative)->not->toBeNull()
        ->and($narrative->kind)->toBe(BookingNarrativeKind::System)
        ->and($narrative->body)->toContain('hold')
        ->and($narrative->body)->toContain('tentative');
});

it('does not append on booking creation', function () {
    $count = $this->booking->narratives()->count();
    expect($count)->toBe(0);
});

it('does not append when status stays the same', function () {
    $this->booking->update(['name' => 'Renamed event']);

    expect($this->booking->narratives()->count())->toBe(0);
});

it('stamps the authenticated user as the narrative author', function () {
    $this->booking->update(['status' => BookingStatus::Definite->value]);

    $narrative = $this->booking->narratives()->latest('id')->first();
    expect($narrative->author_user_id)->toBe($this->user->id);
});

// ---------- Contract lifecycle ----------

it('appends a system narrative when a contract is sent', function () {
    $contract = Contract::factory()->create([
        'booking_id' => $this->booking->id,
        'status' => ContractStatus::Draft->value,
    ]);

    $contract->update(['status' => ContractStatus::Sent->value]);

    $narrative = $this->booking->narratives()->latest('id')->first();
    expect($narrative)->not->toBeNull()
        ->and($narrative->body)->toContain('sent for signature')
        ->and($narrative->body)->toContain($contract->reference);
});

it('appends a system narrative when a contract is fully signed', function () {
    $contract = Contract::factory()->create([
        'booking_id' => $this->booking->id,
        'status' => ContractStatus::Sent->value,
    ]);

    $contract->update(['status' => ContractStatus::Signed->value]);

    $narrative = $this->booking->narratives()->latest('id')->first();
    expect($narrative->body)->toContain('fully signed');
});

it('appends a system narrative when a contract is declined', function () {
    $contract = Contract::factory()->create([
        'booking_id' => $this->booking->id,
        'status' => ContractStatus::Sent->value,
    ]);

    $contract->update(['status' => ContractStatus::Declined->value]);

    $narrative = $this->booking->narratives()->latest('id')->first();
    expect($narrative->body)->toContain('declined');
});

it('does not append on a contract change with no transition verb', function () {
    Contract::factory()->create([
        'booking_id' => $this->booking->id,
        'status' => ContractStatus::Draft->value,
    ]);

    expect($this->booking->narratives()->count())->toBe(0);
});

// ---------- Invoice issuance + refunds ----------

it('appends a system narrative when a booking invoice is issued', function () {
    $invoice = app(InvoiceService::class)->issueDepositForBooking($this->booking->fresh());

    $narrative = $this->booking->narratives()
        ->where('body', 'like', "%{$invoice->number}%")
        ->first();

    expect($narrative)->not->toBeNull()
        ->and($narrative->body)->toContain('issued')
        ->and($narrative->body)->toContain('deposit');
});

it('appends a system narrative when a booking invoice receives a refund', function () {
    $service = app(InvoiceService::class);

    $invoice = $service->issueDepositForBooking($this->booking->fresh());
    $service->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    $beforeCount = $this->booking->narratives()->count();

    $service->refundInvoice($invoice->fresh(), 10_000_00, 'Booking date moved');

    $narrative = $this->booking->narratives()
        ->where('body', 'like', '%Refund of%')
        ->first();

    expect($narrative)->not->toBeNull()
        ->and($narrative->body)->toContain('10,000.00')
        ->and($narrative->body)->toContain($invoice->number)
        ->and($this->booking->narratives()->count())->toBe($beforeCount + 1);
});

it('does not append a narrative when paid_cents increases (a payment, not a refund)', function () {
    $service = app(InvoiceService::class);
    $invoice = $service->issueDepositForBooking($this->booking->fresh());

    $countBefore = $this->booking->narratives()->count();

    $service->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    expect($this->booking->narratives()->count())->toBe($countBefore);
});

// ---------- System kind label ----------

it('exposes the System kind on the booking show payload', function () {
    BookingNarrative::factory()->create([
        'booking_id' => $this->booking->id,
        'kind' => BookingNarrativeKind::System->value,
        'body' => 'Sanity check',
    ]);

    $response = $this->get("/bookings/{$this->booking->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('bookings/show')
        ->where('narratives.0.kind', 'system')
        ->where('narratives.0.kind_label', 'System'));
});
