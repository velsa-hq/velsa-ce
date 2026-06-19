<?php

use App\Enums\ImportStatus;
use App\Models\ChartOfAccount;
use App\Models\ImportJob;
use App\Services\Import\ImportRegistry;
use App\Services\Import\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

const COA_CSV = <<<'CSV'
code,name,type,subtype,parent,postable
1000,Assets,asset,,,no
1010,Cash - Operating,asset,cash,1000,yes
4000,Revenue,revenue,,,no
4100,Rental Revenue,revenue,,4000,
9999,Bad Type,wizard,,,
,No Code Here,asset,,,
1010,Duplicate Cash,asset,,1000,
5500,Orphan,expense,,7777,
CSV;

function uploadCoaCsv(): ImportJob
{
    $admin = grantSuperAdmin();

    test()->actingAs($admin)->post('/admin/imports', [
        'kind' => 'chart-of-accounts',
        'file' => UploadedFile::fake()->createWithContent('coa.csv', COA_CSV),
        'has_header' => true,
    ])->assertRedirect();

    return ImportJob::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Storage::fake('local');
});

it('auto-maps the chart-of-accounts columns', function () {
    $job = uploadCoaCsv();

    expect($job->column_map['code'])->toBe('code')
        ->and($job->column_map['name'])->toBe('name')
        ->and($job->column_map['account_type'])->toBe('type')
        ->and($job->column_map['account_subtype'])->toBe('subtype')
        ->and($job->column_map['parent_code'])->toBe('parent')
        ->and($job->column_map['is_postable'])->toBe('postable');
});

it('previews, resolving same-file parents and flagging bad rows', function () {
    $job = uploadCoaCsv();

    $this->actingAs($job->creator)
        ->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map])
        ->assertRedirect();

    $job->refresh();

    // 4 valid (1000, 1010, 4000, 4100); 4 errors (bad type, no code, dup, orphan parent)
    expect($job->status)->toBe(ImportStatus::Previewed)
        ->and($job->total_rows)->toBe(8)
        ->and($job->valid_rows)->toBe(4)
        ->and($job->error_rows)->toBe(4)
        ->and(ChartOfAccount::count())->toBe(0);

    $messages = $job->errors()->pluck('message')->implode(' | ');
    expect($messages)->toContain('Unrecognized account type')
        ->and($messages)->toContain('Duplicate account code')
        ->and($messages)->toContain('Parent code "7777" not found');
});

it('commits accounts, resolving parents and deriving normal balance', function () {
    $job = uploadCoaCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit")->assertRedirect();

    $job->refresh();
    expect($job->status)->toBe(ImportStatus::Completed)
        ->and($job->created_rows)->toBe(4)
        ->and(ChartOfAccount::count())->toBe(4);

    $assets = ChartOfAccount::where('code', '1000')->firstOrFail();
    $cash = ChartOfAccount::where('code', '1010')->firstOrFail();
    $rental = ChartOfAccount::where('code', '4100')->firstOrFail();

    expect($cash->parent_account_id)->toBe($assets->id)        // parent resolved by code
        ->and($cash->normal_balance)->toBe('debit')            // asset
        ->and($rental->normal_balance)->toBe('credit')         // revenue
        ->and($assets->is_postable)->toBeFalse()               // "no"
        ->and($cash->is_postable)->toBeTrue()                  // "yes"
        ->and($rental->is_postable)->toBeTrue();               // blank -> default yes
});

it('reverses a committed chart-of-accounts import', function () {
    $job = uploadCoaCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    expect(ChartOfAccount::count())->toBe(4);

    app(ImportService::class)->reverse($job->refresh());

    expect(ChartOfAccount::count())->toBe(0)
        ->and($job->refresh()->status)->toBe(ImportStatus::Reversed);
});

it('keeps an imported account that gained an outside child', function () {
    $job = uploadCoaCsv();
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/preview", ['column_map' => $job->column_map]);
    $this->actingAs($job->creator)->post("/admin/imports/{$job->id}/commit");

    // add an outside account under the imported 1000
    $assets = ChartOfAccount::where('code', '1000')->firstOrFail();
    ChartOfAccount::factory()->create([
        'code' => '1099',
        'parent_account_id' => $assets->id,
        'account_type' => 'asset',
    ]);

    app(ImportService::class)->reverse($job->refresh());

    // 1000 stays (has the outside child); revenue branch is gone
    expect(ChartOfAccount::where('code', '1000')->exists())->toBeTrue()
        ->and(ChartOfAccount::where('code', '4000')->exists())->toBeFalse()
        ->and(ChartOfAccount::where('code', '4100')->exists())->toBeFalse()
        ->and($job->refresh()->summary_json['reversal']['skipped'])->toBeGreaterThan(0);
});

it('registers chart-of-accounts as an import kind', function () {
    $registry = app(ImportRegistry::class);

    expect($registry->has('chart-of-accounts'))->toBeTrue()
        ->and($registry->get('chart-of-accounts')?->label())->toBe('Chart of accounts');
});
