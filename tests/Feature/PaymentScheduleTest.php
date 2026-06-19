<?php

use App\Enums\InvoiceStatus;
use App\Mail\IssuedInvoice;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\PaymentSchedule;
use App\Services\Accounting\PaymentScheduleService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin();
});

function bookingWithTotal(int $totalCents = 100_000_00): Booking
{
    return Booking::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'total_cents' => $totalCents,
        'deposit_percent' => 0,
    ]);
}

it('allows an intentional installments-vs-total mismatch (informational only)', function () {
    // schedule may intentionally cover only part of the total; server does not reject
    $booking = bookingWithTotal(100_000_00);

    $this->actingAs($this->user)
        ->put("/bookings/{$booking->id}/payment-schedule", [
            'installments' => [
                ['sequence' => 1, 'due_date' => now()->addMonth()->toDateString(), 'amount_cents' => 40_000_00],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('payment_schedules', ['booking_id' => $booking->id]);
});

it('replaces installments and recomputes the cached schedule total', function () {
    $booking = bookingWithTotal();
    $service = app(PaymentScheduleService::class);

    $schedule = $service->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => '2026-06-01', 'amount_cents' => 30_000_00, 'label' => 'Deposit'],
        ['sequence' => 2, 'due_date' => '2026-09-01', 'amount_cents' => 70_000_00, 'label' => 'Balance'],
    ]);

    expect($schedule->installments)->toHaveCount(2)
        ->and($schedule->total_cents)->toBe(100_000_00);
});

it('a second replace drops un-invoiced installments missing from the new list', function () {
    $booking = bookingWithTotal();
    $service = app(PaymentScheduleService::class);

    $service->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => '2026-06-01', 'amount_cents' => 30_000_00],
        ['sequence' => 2, 'due_date' => '2026-09-01', 'amount_cents' => 70_000_00],
    ]);

    $service->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => '2026-06-15', 'amount_cents' => 50_000_00],
    ]);

    $schedule = $booking->fresh()->paymentSchedule()->first();
    expect($schedule->installments()->count())->toBe(1)
        ->and($schedule->total_cents)->toBe(50_000_00);
});

it('refuses to drop an installment that has already been invoiced', function () {
    $booking = bookingWithTotal();
    $service = app(PaymentScheduleService::class);

    $service->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);

    Artisan::call('installments:issue-due');

    expect(fn () => $service->replaceInstallments($booking, []))
        ->toThrow(RuntimeException::class);
});

it('IssueDueInstallments issues invoices for installments past their due_date', function () {
    $booking = bookingWithTotal();
    $service = app(PaymentScheduleService::class);

    $service->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDays(2)->toDateString(), 'amount_cents' => 30_000_00, 'label' => 'Deposit'],
        ['sequence' => 2, 'due_date' => now()->addMonths(2)->toDateString(), 'amount_cents' => 70_000_00, 'label' => 'Balance'],
    ]);

    Artisan::call('installments:issue-due');

    $installments = Installment::query()->orderBy('sequence')->get();
    expect($installments[0]->invoice_id)->not->toBeNull()
        ->and($installments[0]->invoiced_at)->not->toBeNull()
        ->and($installments[1]->invoice_id)->toBeNull();

    $invoice = Invoice::query()->find($installments[0]->invoice_id);
    expect($invoice->total_cents)->toBe(30_000_00)
        ->and($invoice->notes)->toBe('installment_1');
});

it('sends an IssuedInvoice email to the booking primary contact on auto-issue', function () {
    Mail::fake();

    $client = Client::factory()->create();
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'email' => 'finance@example.com',
    ]);

    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 0,
    ]);
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);

    Artisan::call('installments:issue-due');

    Mail::assertSent(IssuedInvoice::class, function (IssuedInvoice $mail) {
        return $mail->hasTo('finance@example.com');
    });

    // idempotent re-run does not re-email
    Mail::fake();
    Artisan::call('installments:issue-due');
    Mail::assertNothingSent();
});

it('skips the IssuedInvoice email silently when the client has no contact email', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 0,
    ]);
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);

    Artisan::call('installments:issue-due');

    Mail::assertNothingSent();
    // but the invoice still gets created
    expect(Invoice::query()->count())->toBe(1);
});

it('is idempotent on repeated runs', function () {
    $booking = bookingWithTotal();
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);

    Artisan::call('installments:issue-due');
    Artisan::call('installments:issue-due');

    expect(Invoice::query()->count())->toBe(1);
});

it('clears invoice_id + invoiced_at on the installment when the invoice is voided', function () {
    $booking = bookingWithTotal();
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);
    Artisan::call('installments:issue-due');

    $installment = Installment::query()->firstOrFail();
    expect($installment->invoice_id)->not->toBeNull()
        ->and($installment->invoiced_at)->not->toBeNull();

    $invoice = $installment->invoice;
    $invoice->update(['status' => InvoiceStatus::Void->value]);

    $fresh = $installment->fresh();
    expect($fresh->invoice_id)->toBeNull()
        ->and($fresh->invoiced_at)->toBeNull();
});

it('marks the installment paid when the issued invoice is fully paid', function () {
    $booking = bookingWithTotal();
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => now()->subDay()->toDateString(), 'amount_cents' => 30_000_00],
    ]);
    Artisan::call('installments:issue-due');

    $installment = Installment::query()->firstOrFail();
    $invoice = $installment->invoice;

    $invoice->update([
        'paid_cents' => $invoice->total_cents,
        'status' => InvoiceStatus::Paid->value,
    ]);

    expect($installment->fresh()->paid_at)->not->toBeNull();
});

it('rejects creating a schedule on a booking that still has a deposit percent', function () {
    $booking = Booking::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);

    $this->actingAs($this->user)
        ->put("/bookings/{$booking->id}/payment-schedule", [
            'installments' => [
                ['sequence' => 1, 'due_date' => '2026-06-01', 'amount_cents' => 30_000_00],
                ['sequence' => 2, 'due_date' => '2026-09-01', 'amount_cents' => 70_000_00],
            ],
        ])
        ->assertSessionHasErrors('installments');

    expect(PaymentSchedule::query()->count())->toBe(0);
});

it('the booking show page exposes the schedule in its Inertia payload', function () {
    $booking = bookingWithTotal();
    app(PaymentScheduleService::class)->replaceInstallments($booking, [
        ['sequence' => 1, 'due_date' => '2026-07-01', 'amount_cents' => 30_000_00, 'label' => 'Deposit'],
        ['sequence' => 2, 'due_date' => '2026-09-01', 'amount_cents' => 70_000_00, 'label' => 'Balance'],
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/show')
            ->has('billing.payment_schedule.installments', 2)
            ->where('billing.payment_schedule.total_cents', 100_000_00));
});
