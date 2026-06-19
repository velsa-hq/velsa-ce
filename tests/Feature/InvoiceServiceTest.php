<?php

use App\Enums\DunningStage;
use App\Enums\ExhibitorOrderStatus;
use App\Enums\InvoiceStatus;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\Invoice;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    $this->order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($this->order, $chair, 10);
    $this->order->recalculateTotals();

    $this->service = app(InvoiceService::class);
});

it('issues an invoice from an order with billable items', function () {
    $invoice = $this->service->issueForOrder($this->order->fresh());

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total_cents)->toBe($this->order->fresh()->total_cents)
        ->and($invoice->due_on->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($invoice->number)->toStartWith('INV-');
});

it('refuses to issue an invoice for an empty order', function () {
    $empty = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
        'subtotal_cents' => 0,
        'tax_cents' => 0,
        'total_cents' => 0,
    ]);

    $this->service->issueForOrder($empty);
})->throws(RuntimeException::class, 'no items');

it('is idempotent - issuing twice returns the same invoice', function () {
    $first = $this->service->issueForOrder($this->order->fresh());
    $second = $this->service->issueForOrder($this->order->fresh());

    expect($second->id)->toBe($first->id)
        ->and(Invoice::query()->count())->toBe(1);
});

it('refreshes paid_cents and status from the source order', function () {
    $invoice = $this->service->issueForOrder($this->order->fresh());

    $this->order->update([
        'paid_cents' => $this->order->total_cents,
        'status' => ExhibitorOrderStatus::Paid->value,
    ]);

    $refreshed = $this->service->refreshFromSource($invoice->fresh());

    expect($refreshed->status)->toBe(InvoiceStatus::Paid)
        ->and($refreshed->paid_cents)->toBe($this->order->fresh()->total_cents)
        ->and($refreshed->paid_at)->not->toBeNull();
});

it('marks the invoice partial_paid when only some payment is captured', function () {
    $invoice = $this->service->issueForOrder($this->order->fresh());
    $half = (int) floor($this->order->total_cents / 2);

    $this->order->update(['paid_cents' => $half]);
    $refreshed = $this->service->refreshFromSource($invoice->fresh());

    expect($refreshed->status)->toBe(InvoiceStatus::PartialPaid)
        ->and($refreshed->paid_cents)->toBe($half);
});

it('voids an open invoice and records the reason', function () {
    $invoice = $this->service->issueForOrder($this->order->fresh());

    $voided = $this->service->void($invoice, 'Issued to wrong exhibitor');

    expect($voided->status)->toBe(InvoiceStatus::Void)
        ->and($voided->voided_at)->not->toBeNull()
        ->and($voided->void_reason)->toBe('Issued to wrong exhibitor');
});

it('refuses to void a fully paid invoice', function () {
    $invoice = $this->service->issueForOrder($this->order->fresh());
    $this->order->update(['paid_cents' => $this->order->total_cents]);
    $this->service->refreshFromSource($invoice->fresh());

    $this->service->void($invoice->fresh(), 'No backsies');
})->throws(RuntimeException::class, 'Cannot void a paid invoice');

it('advances dunning to first_notice within 1-7 days past due', function () {
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create();

    $advanced = $this->service->advanceDunning($invoice);

    expect($advanced)->not->toBeNull()
        ->and($advanced->dunning_stage)->toBe(DunningStage::FirstNotice);
});

it('advances dunning to collections beyond 60 days past due', function () {
    $invoice = Invoice::factory()->pastDue(daysOverdue: 75)->create();

    $advanced = $this->service->advanceDunning($invoice);

    expect($advanced?->dunning_stage)->toBe(DunningStage::Collections);
});

it('returns null when dunning stage is already correct', function () {
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create();
    $this->service->advanceDunning($invoice);

    $result = $this->service->advanceDunning($invoice->fresh());

    expect($result)->toBeNull();
});

it('skips dunning advancement on a paid invoice', function () {
    $invoice = Invoice::factory()->paid()->create();

    expect($this->service->advanceDunning($invoice))->toBeNull();
});
