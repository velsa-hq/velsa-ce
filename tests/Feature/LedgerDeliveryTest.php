<?php

use App\Mail\LedgerBatchMail;
use App\Models\ExportTemplate;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Models\User;
use App\Services\Accounting\BatchDeliveryService;
use App\Services\Accounting\LedgerExporter;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExportTemplatesSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(ExportTemplatesSeeder::class);
});

/** post a balanced pair and export it into a ready batch */
function readyBatch(): LedgerExportBatch
{
    JournalEntry::post(['account_code' => '1010', 'description' => 'rcpt', 'debit_cents' => 5000, 'posted_on' => '2026-03-04']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'rev', 'credit_cents' => 5000, 'posted_on' => '2026-03-04']);

    return app(LedgerExporter::class)->exportPeriod('2026-03');
}

it('leaves a batch ready when no transport is configured', function () {
    config(['accounting.export.transport' => 'none']);
    $batch = readyBatch();

    $batch = app(BatchDeliveryService::class)->deliver($batch);

    expect($batch->status)->toBe('ready');
    expect($batch->sent_at)->toBeNull();
    expect($batch->delivery_transport)->toBe('none');
});

it('emails the batch and marks it sent', function () {
    Mail::fake();
    config([
        'accounting.export.transport' => 'email',
        'accounting.export.email.recipient' => 'gl@county.test',
    ]);
    $batch = readyBatch();

    $batch = app(BatchDeliveryService::class)->deliver($batch);

    Mail::assertSent(LedgerBatchMail::class);
    expect($batch->status)->toBe('sent');
    expect($batch->sent_at)->not->toBeNull();
    expect($batch->delivery_transport)->toBe('email');
    expect($batch->delivery_detail)->toContain('gl@county.test');
});

it('marks the batch failed when email transport has no recipient', function () {
    Mail::fake();
    config([
        'accounting.export.transport' => 'email',
        'accounting.export.email.recipient' => null,
    ]);
    $batch = readyBatch();

    $batch = app(BatchDeliveryService::class)->deliver($batch);

    Mail::assertNothingSent();
    expect($batch->status)->toBe('failed');
    expect($batch->failure_reason)->not->toBeNull();
});

it('exports through the chosen template and delivers in one request', function () {
    config(['accounting.export.transport' => 'none']);
    JournalEntry::post(['account_code' => '1010', 'description' => 'a', 'debit_cents' => 100, 'posted_on' => '2026-03-09']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'b', 'credit_cents' => 100, 'posted_on' => '2026-03-09']);
    $template = ExportTemplate::query()->orderByDesc('id')->firstOrFail();

    $this->actingAs(grantSuperAdmin())
        ->post('/accounting/export', [
            'period' => '2026-03',
            'export_template_id' => $template->id,
        ])
        ->assertRedirect();

    $batch = LedgerExportBatch::query()->latest('id')->firstOrFail();
    expect($batch->export_template_id)->toBe($template->id);
    expect($batch->status)->toBe('ready');
});

it('acknowledges a batch', function () {
    $batch = readyBatch();

    $this->actingAs(grantSuperAdmin())
        ->post("/accounting/batches/{$batch->id}/acknowledge")
        ->assertRedirect();

    $batch->refresh();
    expect($batch->status)->toBe('acknowledged');
    expect($batch->acknowledged_at)->not->toBeNull();
});

it('re-attempts delivery on resend', function () {
    Mail::fake();
    config([
        'accounting.export.transport' => 'email',
        'accounting.export.email.recipient' => 'gl@county.test',
    ]);
    $batch = readyBatch();

    $this->actingAs(grantSuperAdmin())
        ->post("/accounting/batches/{$batch->id}/resend")
        ->assertRedirect();

    Mail::assertSent(LedgerBatchMail::class);
    expect($batch->fresh()->status)->toBe('sent');
});

it('gates acknowledge + resend behind accounting.export_ledger', function () {
    $batch = readyBatch();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/accounting/batches/{$batch->id}/acknowledge")
        ->assertForbidden();

    $this->actingAs($user)
        ->post("/accounting/batches/{$batch->id}/resend")
        ->assertForbidden();
});
