<?php

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Reports\Handlers\ClerkMonthlyArReport;
use App\Reports\ReportRegistry;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('lists invoices issued in the chosen month', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    $invoice->forceFill(['issued_on' => now()->subMonth()->startOfMonth()->addDays(5)])->save();

    $result = app(ClerkMonthlyArReport::class)
        ->run(['month' => now()->subMonth()->format('Y-m')]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['number'])->toBe($invoice->number);
});

it('excludes invoices issued outside the window', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    $invoice->forceFill(['issued_on' => now()->subMonths(2)->startOfMonth()->addDays(5)])->save();

    $result = app(ClerkMonthlyArReport::class)
        ->run(['month' => now()->subMonth()->format('Y-m')]);

    expect($result->rows)->toBeEmpty();
});

it('rolls movement totals from journal entries against the AR account', function () {
    $month = now()->subMonth();
    // issuance: debit AR 1100
    JournalEntry::post([
        'account_code' => '1100',
        'description' => 'Test issuance',
        'debit_cents' => 50_000_00,
        'posted_on' => $month->copy()->startOfMonth()->addDays(2)->toDateString(),
    ]);
    // payment: credit AR 1100
    JournalEntry::post([
        'account_code' => '1100',
        'description' => 'Test payment',
        'credit_cents' => 20_000_00,
        'posted_on' => $month->copy()->startOfMonth()->addDays(10)->toDateString(),
    ]);
    // write-off: debit 5900 bad debt
    JournalEntry::post([
        'account_code' => '5900',
        'description' => 'Test write-off',
        'debit_cents' => 5_000_00,
        'posted_on' => $month->copy()->startOfMonth()->addDays(15)->toDateString(),
    ]);

    $result = app(ClerkMonthlyArReport::class)
        ->run(['month' => $month->format('Y-m')]);

    $summary = collect($result->summary)->keyBy('label');
    expect($summary['AR debits (issued + refunds)']['value'])->toBe('$50,000.00')
        ->and($summary['AR credits (payments + write-offs)']['value'])->toBe('$20,000.00')
        ->and($summary['Write-offs']['value'])->toBe('$5,000.00');
});

it('computes period-end aging from open invoices as of period end', function () {
    $month = now()->subMonth();
    $periodEnd = $month->copy()->endOfMonth();

    $client = Client::factory()->create();
    $booking = Booking::factory()->create(['client_id' => $client->id, 'total_cents' => 50_000_00]);

    // 100 days past due at period end -> 90+ bucket
    Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $booking->id,
        'status' => InvoiceStatus::PastDue->value,
        'total_cents' => 10_000_00,
        'paid_cents' => 0,
        'issued_on' => $periodEnd->copy()->subDays(150),
        'due_on' => $periodEnd->copy()->subDays(100),
    ]);
    // 15 days past due at period end -> 1-30 bucket
    Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $booking->id,
        'status' => InvoiceStatus::PastDue->value,
        'total_cents' => 3_000_00,
        'paid_cents' => 0,
        'issued_on' => $periodEnd->copy()->subDays(40),
        'due_on' => $periodEnd->copy()->subDays(15),
    ]);

    $result = app(ClerkMonthlyArReport::class)
        ->run(['month' => $month->format('Y-m')]);

    $summary = collect($result->summary)->keyBy('label');
    expect($summary['Aged 90+']['value'])->toBe('$10,000.00')
        ->and($summary['Aged 1-30']['value'])->toBe('$3,000.00');
});

it('is registered with the report registry', function () {
    expect(app(ReportRegistry::class)->has('clerk-monthly-ar'))->toBeTrue();
});
