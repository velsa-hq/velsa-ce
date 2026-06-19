<?php

use App\Enums\BookingStatus;
use App\Enums\ExhibitorOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Venue;
use App\Services\Accounting\InvoiceService;
use App\Services\BookingSettlement;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('returns booking-fee charges for a bare booking', function () {
    $booking = Booking::factory()->create(['total_cents' => 5_000_00]);

    $data = app(BookingSettlement::class)->forBooking($booking);

    expect($data['charges'])->toHaveCount(1)
        ->and($data['charges'][0]['amount_cents'])->toBe(5_000_00)
        ->and($data['charges_subtotal_cents'])->toBe(5_000_00);
});

it('rolls up exhibitor orders into the charges block', function () {
    $booking = Booking::factory()->create(['total_cents' => 1_000_00]);
    $event = ExhibitorEvent::factory()->create(['booking_id' => $booking->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'total_cents' => 2_500_00,
    ]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'total_cents' => 1_500_00,
    ]);

    $data = app(BookingSettlement::class)->forBooking($booking);

    expect($data['charges'])->toHaveCount(2)
        ->and($data['charges'][1]['amount_cents'])->toBe(4_000_00)
        ->and($data['charges_subtotal_cents'])->toBe(5_000_00);
});

it('lists invoices issued against the booking and its exhibitor orders', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    app(InvoiceService::class)->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    $data = app(BookingSettlement::class)->forBooking($booking->fresh());

    expect($data['invoices'])->toHaveCount(1)
        ->and($data['invoices'][0]['number'])->toBe($invoice->number)
        ->and($data['totals']['invoiced_cents'])->toBe($invoice->total_cents)
        ->and($data['totals']['paid_cents'])->toBe($invoice->total_cents)
        ->and($data['totals']['outstanding_cents'])->toBe(0);
});

it('reflects refunds in net collected', function () {
    $booking = Booking::factory()->create(['total_cents' => 1_000_00]);
    $event = ExhibitorEvent::factory()->create(['booking_id' => $booking->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    // paid then partially refunded: order/invoice walked back, payment ledger keeps the refund
    $order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::PartiallyPaid->value,
        'total_cents' => 5_000_00,
        'paid_cents' => 4_000_00,
    ]);
    Invoice::factory()->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $order->id,
        'total_cents' => 5_000_00,
        'paid_cents' => 4_000_00,
    ]);
    ExhibitorPayment::factory()->create([
        'exhibitor_order_id' => $order->id,
        'amount_cents' => 5_000_00,
        'refunded_amount_cents' => 1_000_00,
        'status' => PaymentStatus::Captured->value,
    ]);

    $data = app(BookingSettlement::class)->forBooking($booking);

    expect($data['payments'])->toHaveCount(1)
        ->and($data['totals']['refunded_cents'])->toBe(1_000_00)
        ->and($data['totals']['net_collected_cents'])->toBe(4_000_00)
        ->and($data['totals']['outstanding_cents'])->toBe(1_000_00);
});

it('renders the settlement PDF view for a user authorized to view the booking', function () {
    Pdf::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRoleAt(Venue::factory()->create(), 'super_admin');
    $booking = Booking::factory()->withStatus(BookingStatus::Completed)->create();

    $response = $this->actingAs($admin)->get("/bookings/{$booking->id}/settlement.pdf");

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) use ($booking) {
        expect($pdf->viewName)->toBe('pdf.booking-settlement')
            ->and($pdf->viewData['booking']->id)->toBe($booking->id);

        return true;
    });
});

it('requires authentication on the settlement endpoint', function () {
    $booking = Booking::factory()->create();

    $this->get("/bookings/{$booking->id}/settlement.pdf")
        ->assertRedirect(route('login'));
});
