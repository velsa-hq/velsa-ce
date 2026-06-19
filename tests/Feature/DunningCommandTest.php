<?php

use App\Enums\DunningStage;
use App\Models\AuditEvent;
use App\Models\Invoice;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);
});

it('advances every open invoice and reports counts', function () {
    Invoice::factory()->pastDue(daysOverdue: 3)->create();
    Invoice::factory()->pastDue(daysOverdue: 45)->create();
    Invoice::factory()->paid()->create(); // skipped

    $this->artisan('invoices:advance-dunning')
        ->expectsOutputToContain('Scanned 2 open invoices')
        ->assertExitCode(0);

    $stages = Invoice::query()->whereNotNull('due_on')->pluck('dunning_stage')->all();
    expect($stages)->toContain(DunningStage::FirstNotice, DunningStage::FinalNotice);
});

it('is idempotent across repeated runs', function () {
    Invoice::factory()->pastDue(daysOverdue: 3)->create();

    $this->artisan('invoices:advance-dunning')->assertExitCode(0);
    $this->artisan('invoices:advance-dunning')
        ->expectsOutputToContain('advanced 0 dunning stage(s)')
        ->assertExitCode(0);
});

it('writes an audit row when an invoice transitions stages', function () {
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create();

    $this->artisan('invoices:advance-dunning')->assertExitCode(0);

    $audit = AuditEvent::query()
        ->where('event_type', 'invoice.dunning_advanced')
        ->where('subject_id', $invoice->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['to_stage'])->toBe('first_notice');
});
