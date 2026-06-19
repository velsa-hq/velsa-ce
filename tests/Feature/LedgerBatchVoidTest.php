<?php

use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Models\User;
use App\Services\Accounting\LedgerExporter;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExportTemplatesSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(ExportTemplatesSeeder::class);
});

/** post a balanced pair and export it into a batch */
function seedBatch(): LedgerExportBatch
{
    JournalEntry::post(['account_code' => '1010', 'description' => 'rcpt', 'debit_cents' => 5000, 'posted_on' => '2026-03-04']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'rev', 'credit_cents' => 5000, 'posted_on' => '2026-03-04']);

    return app(LedgerExporter::class)->exportPeriod('2026-03');
}

it('shows a batch detail page with its claimed entries', function () {
    $batch = seedBatch();

    $this->actingAs(grantSuperAdmin())
        ->get("/accounting/batches/{$batch->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/batch-detail')
            ->where('batch.period', '2026-03')
            ->where('batch.status', 'ready')
            ->has('entries', 2));
});

it('voids a batch and detaches its entries back to pending', function () {
    $batch = seedBatch();
    expect(JournalEntry::whereNotNull('export_batch_id')->count())->toBe(2);

    $this->actingAs(grantSuperAdmin())
        ->post("/accounting/batches/{$batch->id}/void", ['reason' => 'wrong period'])
        ->assertRedirect('/accounting');

    $batch->refresh();
    expect($batch->status)->toBe('voided');
    expect($batch->voided_at)->not->toBeNull();
    expect($batch->void_reason)->toBe('wrong period');
    expect(JournalEntry::whereNull('export_batch_id')->count())->toBe(2);
    expect(JournalEntry::whereNotNull('export_batch_id')->count())->toBe(0);
});

it('requires a reason to void', function () {
    $batch = seedBatch();

    $this->actingAs(grantSuperAdmin())
        ->post("/accounting/batches/{$batch->id}/void", [])
        ->assertSessionHasErrors('reason');
});

it('refuses to void an already-voided batch', function () {
    $batch = seedBatch();
    $admin = grantSuperAdmin();

    $this->actingAs($admin)
        ->post("/accounting/batches/{$batch->id}/void", ['reason' => 'first']);

    $this->actingAs($admin)
        ->post("/accounting/batches/{$batch->id}/void", ['reason' => 'again'])
        ->assertStatus(422);
});

it('gates void + export behind accounting.export_ledger', function () {
    $batch = seedBatch();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post("/accounting/batches/{$batch->id}/void", ['reason' => 'nope'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/accounting/export', ['period' => '2026-03'])
        ->assertForbidden();
});
