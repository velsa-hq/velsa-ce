<?php

use App\Enums\ContractStatus;
use App\Enums\TemplateKind;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\DocumentTemplate;
use App\Models\Space;
use App\Services\Signing\ContractDispatcher;
use App\Services\Signing\FakeSignatureProvider;
use App\Services\Signing\SignatureProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('drafts a contract from a booking and renders the matching template', function () {
    $template = DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => null,
        'body_html' => '<h1>{{booking.name}}</h1><p>{{client.name}} · {{booking.total}}</p>',
    ]);
    $booking = Booking::factory()->create([
        'name' => 'Test Booking',
        'total_cents' => 1_250_00,
    ]);

    $contract = app(ContractDispatcher::class)->draftFromBooking($booking);

    expect($contract->template_id)->toBe($template->id)
        ->and($contract->status)->toBe(ContractStatus::Draft)
        ->and($contract->rendered_html)->toContain('Test Booking')
        ->and($contract->rendered_html)->toContain('$1,250.00');
});

it('renders booked spaces via {{spaces}}', function () {
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => null,
        'body_html' => '<p>Spaces: {{spaces}}</p>',
    ]);
    $booking = Booking::factory()->create();
    $ballroom = Space::factory()->create(['name' => 'Grand Ballroom']);
    $room = Space::factory()->create(['name' => 'Room 201']);
    BookingSpace::factory()->create(['booking_id' => $booking->id, 'space_id' => $ballroom->id]);
    BookingSpace::factory()->create(['booking_id' => $booking->id, 'space_id' => $room->id]);

    $contract = app(ContractDispatcher::class)->draftFromBooking($booking);

    expect($contract->rendered_html)->toContain('Grand Ballroom')
        ->and($contract->rendered_html)->toContain('Room 201');
});

it('forVenueKind prefers a venue-specific template over a global', function () {
    $booking = Booking::factory()->create(['name' => 'Venue-specific event']);

    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => null,
        'body_html' => '<p>GLOBAL contract for {{booking.name}}</p>',
        'is_active' => true,
    ]);
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => $booking->venue_id,
        'body_html' => '<p>VENUE-SPECIFIC contract for {{booking.name}}</p>',
        'is_active' => true,
    ]);

    $contract = app(ContractDispatcher::class)->draftFromBooking($booking);

    expect($contract->rendered_html)->toContain('VENUE-SPECIFIC')
        ->and($contract->rendered_html)->not->toContain('GLOBAL');
});

it('forVenueKind skips inactive templates', function () {
    $booking = Booking::factory()->create(['name' => 'Skip-inactive event']);

    // inactive venue-specific - should not be picked
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => $booking->venue_id,
        'body_html' => '<p>INACTIVE venue-specific for {{booking.name}}</p>',
        'is_active' => false,
    ]);
    // active global - picked after the inactive match is filtered
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => null,
        'body_html' => '<p>ACTIVE GLOBAL for {{booking.name}}</p>',
        'is_active' => true,
    ]);

    $contract = app(ContractDispatcher::class)->draftFromBooking($booking);

    expect($contract->rendered_html)->toContain('ACTIVE GLOBAL')
        ->and($contract->rendered_html)->not->toContain('INACTIVE');
});

it('drafts with a null template when nothing matches', function () {
    $booking = Booking::factory()->create();

    $contract = app(ContractDispatcher::class)->draftFromBooking($booking);

    expect($contract->template_id)->toBeNull()
        ->and($contract->rendered_html)->toBeEmpty();
});

it('send() creates signers, persists the envelope id, and emits an audit row', function () {
    $contract = Contract::factory()->create(['status' => ContractStatus::Draft->value]);

    $envelope = app(ContractDispatcher::class)->send($contract, [
        ['name' => 'Alice Client', 'email' => 'alice@example.test'],
        ['name' => 'Bob Co-Signer', 'email' => 'bob@example.test', 'signing_order' => 2],
    ]);

    $fresh = $contract->fresh('signers');

    expect($fresh->status)->toBe(ContractStatus::Sent)
        ->and($fresh->provider_envelope_id)->toBe($envelope->envelopeId)
        ->and($fresh->signers)->toHaveCount(2)
        ->and($fresh->signers->first()->provider_recipient_id)->not->toBeNull()
        ->and(AuditEvent::query()->where('event_type', 'contract.sent')->count())->toBe(1);
});

it('binds SignatureProvider to FakeSignatureProvider', function () {
    expect(app(SignatureProvider::class))->toBeInstanceOf(FakeSignatureProvider::class);
});

it('refuses to send a contract that is not in Draft status', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create();

    // markSent returns false past Draft; status stays unchanged
    app(ContractDispatcher::class)->send($contract, [['name' => 'A', 'email' => 'a@test']]);

    expect($contract->fresh()->status)->toBe(ContractStatus::Sent);
});

it('signs end-to-end by simulating provider callbacks', function () {
    $contract = Contract::factory()->create(['status' => ContractStatus::Draft->value]);
    /** @var FakeSignatureProvider $provider */
    $provider = app(SignatureProvider::class);

    $envelope = app(ContractDispatcher::class)->send($contract, [
        ['name' => 'Solo Signer', 'email' => 'solo@example.test'],
    ]);

    // view event via provider callback
    $provider->simulateView($envelope->envelopeId);
    $signer = ContractSigner::query()->where('contract_id', $contract->id)->firstOrFail();
    $contract->markViewedBy($signer);
    expect($contract->fresh()->status)->toBe(ContractStatus::Viewed);

    // then the sign event
    $provider->simulateSign($envelope->envelopeId);
    $contract->markSignedBy($signer);
    expect($contract->fresh()->status)->toBe(ContractStatus::Signed);
});
