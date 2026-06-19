<?php

use App\Enums\ExhibitorOrderStatus;
use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\JournalEntry;
use App\Models\Venue;
use App\Services\Payments\OrderPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('posts a balanced two-leg journal entry when a payment captures (DR Cash / CR A/R)', function () {
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    $event = ExhibitorEvent::factory()->create(['booking_id' => $booking->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    $order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    app(OrderPaymentService::class)->charge($order, 'visa_4242');

    $entries = JournalEntry::query()->orderBy('id')->get();
    expect($entries->count())->toBe(2)
        ->and($entries[0]->account_code)->toBe('1010')
        ->and($entries[0]->debit_cents)->toBe(100_00)
        ->and($entries[0]->credit_cents)->toBe(0)
        ->and($entries[1]->account_code)->toBe('1100')
        ->and($entries[1]->debit_cents)->toBe(0)
        ->and($entries[1]->credit_cents)->toBe(100_00);
});

it('inherits the booking venue_id onto both journal entries', function () {
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    $event = ExhibitorEvent::factory()->create(['booking_id' => $booking->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    $order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'total_cents' => 50_00,
    ]);

    app(OrderPaymentService::class)->charge($order, 'visa_4242');

    expect(JournalEntry::query()->pluck('venue_id')->all())
        ->toBe([$venue->id, $venue->id]);
});

it('does not post journal entries on a declined charge', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    app(OrderPaymentService::class)->charge($order, 'test_0000');

    expect(JournalEntry::query()->count())->toBe(0);
});
