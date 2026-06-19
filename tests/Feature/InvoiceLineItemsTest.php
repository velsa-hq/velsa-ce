<?php

use App\Enums\ExhibitorOrderStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('keeps line_total_cents in sync with quantity x unit_price', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    $line = $invoice->lines->first();

    $line->update(['quantity' => 5, 'unit_price_cents' => 1_000_00]);

    expect($line->fresh()->line_total_cents)->toBe(5 * 1_000_00);
});

it('fans an exhibitor order out into one line per item on issue', function () {
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
    ]);
    $order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    $table = EquipmentItem::query()->where('sku', 'TABLE-6FT')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($order, $chair, 10);
    ExhibitorOrderItem::fromCatalog($order, $table, 4);
    $order->recalculateTotals();

    $invoice = app(InvoiceService::class)->issueForOrder($order->fresh());

    expect($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines->pluck('quantity')->all())->toBe([10, 4])
        ->and($invoice->lines->sum('line_total_cents'))->toBe($invoice->subtotal_cents);
});

it('writes a single descriptive line for booking-deposit invoices', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'name' => 'Acme Summer Gala',
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);

    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    expect($invoice->lines)->toHaveCount(1)
        ->and($invoice->lines->first()->description)
        ->toContain('Booking deposit')
        ->toContain($booking->reference);
});

it('writes a balance-line for booking-balance invoices', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $balance = app(InvoiceService::class)->issueBalanceForBooking($booking->fresh());

    expect($balance->lines)->toHaveCount(1)
        ->and($balance->lines->first()->description)->toContain('Booking balance');
});

it('saves customer + internal references via the inline edit endpoint', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $this->actingAs($admin)->patch("/admin/invoices/{$invoice->number}/references", [
        'customer_reference' => 'PO-12345',
        'internal_reference' => 'PROJ-2026-042',
    ])->assertRedirect();

    $fresh = $invoice->fresh();
    expect($fresh->customer_reference)->toBe('PO-12345')
        ->and($fresh->internal_reference)->toBe('PROJ-2026-042');
});

it('clears references when blank values are submitted', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    $invoice->forceFill([
        'customer_reference' => 'PO-OLD',
        'internal_reference' => 'OLD',
    ])->save();

    $this->actingAs($admin)->patch("/admin/invoices/{$invoice->number}/references", [
        'customer_reference' => '',
        'internal_reference' => '',
    ])->assertRedirect();

    $fresh = $invoice->fresh();
    expect($fresh->customer_reference)->toBeNull()
        ->and($fresh->internal_reference)->toBeNull();
});

it('exposes the lines + references in the show page Inertia payload', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());
    $invoice->forceFill(['customer_reference' => 'PO-99'])->save();

    $response = $this->actingAs($admin)->get("/admin/invoices/{$invoice->number}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/invoices/show')
        ->has('invoice.lines', 1)
        ->where('invoice.customer_reference', 'PO-99'));
});
