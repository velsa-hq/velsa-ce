<?php

use App\Enums\ExhibitorOrderStatus;
use App\Mail\InvoiceRefunded;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Services\Accounting\InvoiceService;
use App\Services\Payments\OrderPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    Mail::fake();
});

it('emails the booking client when a booking invoice is refunded', function () {
    $client = Client::factory()->create(['name' => 'Acme Inc']);
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'name' => 'Casey Client',
        'email' => 'casey@acme.test',
    ]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->issueDepositForBooking($booking->fresh());
    $service->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    $service->refundInvoice($invoice->fresh(), 10_000_00, 'Scope reduced');

    Mail::assertSent(InvoiceRefunded::class, function (InvoiceRefunded $mail) use ($invoice) {
        return $mail->hasTo('casey@acme.test')
            && $mail->amountCents === 10_000_00
            && $mail->reason === 'Scope reduced'
            && $mail->invoice->id === $invoice->id;
    });
});

it('emails the exhibitor when an order payment is refunded', function () {
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
        'email' => 'vendor@example.test',
        'contact_name' => 'Vera Vendor',
    ]);
    $order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($order, $item, 10);
    $order->recalculateTotals();

    app(InvoiceService::class)->issueForOrder($order->fresh());
    $payment = app(OrderPaymentService::class)->charge(
        $order->fresh(),
        'visa_tok_42424242',
        $order->fresh()->balanceCents(),
    );

    $refundAmount = (int) floor($payment->amount_cents / 2);
    app(OrderPaymentService::class)->refund(
        $payment,
        $refundAmount,
        reason: 'Partial cancellation',
    );

    Mail::assertSent(InvoiceRefunded::class, function (InvoiceRefunded $mail) use ($refundAmount) {
        return $mail->hasTo('vendor@example.test')
            && $mail->amountCents === $refundAmount
            && $mail->reason === 'Partial cancellation';
    });
});

it('skips the email when no contact email is on file', function () {
    $client = Client::factory()->create();
    // no contact rows -> no email to resolve
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->issueDepositForBooking($booking->fresh());
    $service->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    $service->refundInvoice($invoice->fresh(), 10_000_00, 'Scope reduced');

    Mail::assertNothingSent();
});

it('renders the refund email body with the new outstanding balance', function () {
    $client = Client::factory()->create();
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'name' => 'Casey Client',
        'email' => 'casey@acme.test',
    ]);
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $service = app(InvoiceService::class);
    $invoice = $service->issueDepositForBooking($booking->fresh());
    $service->applyPaymentToInvoice($invoice, $invoice->total_cents, 'check');

    // refund a quarter of the deposit
    $refund = (int) floor($invoice->paid_cents / 4);
    $refreshed = $service->refundInvoice($invoice->fresh(), $refund, 'Adjustment');

    $rendered = (new InvoiceRefunded($refreshed, $refund, 'Adjustment'))->render();
    $balance = $refreshed->balanceCents();

    expect($rendered)->toContain('Refund issued')
        ->toContain('Casey Client')
        ->toContain('Adjustment')
        ->toContain(number_format($refund / 100, 2))
        ->toContain(number_format($balance / 100, 2));
});
