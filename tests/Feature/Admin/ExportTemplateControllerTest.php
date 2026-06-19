<?php

use App\Enums\ExportFormat;
use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Models\User;
use App\Services\Accounting\ExportSource;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExportTemplatesSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(ExportTemplatesSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists existing templates on the index page', function () {
    $response = $this->actingAs($this->user)->get('/admin/export-templates');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/export-templates/index')
            ->has('templates', 2)
            ->where('templates.0.is_default', true)
        );
});

it('renders the create page with metadata + starter template', function () {
    $response = $this->actingAs($this->user)->get('/admin/export-templates/create');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/export-templates/create')
            ->has('metadata.formats')
            ->has('metadata.sources')
            ->has('metadata.format_masks')
            ->has('template.columns')
        );
});

it('stores a new template with columns', function () {
    $payload = [
        'name' => 'Custom Test Template',
        'description' => 'Just a test',
        'format' => ExportFormat::Csv->value,
        'delimiter' => '|',
        'quote_char' => '"',
        'line_ending' => 'crlf',
        'encoding' => 'utf-8',
        'include_header' => true,
        'include_footer' => false,
        'is_default' => false,
        'file_extension' => 'csv',
        'columns' => [
            ['label' => 'Acct', 'source' => ExportSource::ACCOUNT_CODE, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
            ['label' => 'Amt', 'source' => ExportSource::DEBIT_CENTS, 'format_mask' => 'money:dot', 'default_value' => null, 'width' => null, 'align' => 'right', 'pad_char' => ' '],
        ],
    ];

    $response = $this->actingAs($this->user)->post('/admin/export-templates', $payload);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    $created = ExportTemplate::where('name', 'Custom Test Template')->firstOrFail();
    expect($created->columns)->toHaveCount(2)
        ->and($created->line_ending)->toBe("\r\n")
        ->and($created->delimiter)->toBe('|');
});

it('rejects a template with no columns', function () {
    $response = $this->actingAs($this->user)->post('/admin/export-templates', [
        'name' => 'No Cols',
        'format' => ExportFormat::Csv->value,
        'line_ending' => 'lf',
        'encoding' => 'utf-8',
        'file_extension' => 'csv',
        'columns' => [],
    ]);

    $response->assertSessionHasErrors(['columns']);
});

it('rejects a template with an unknown source', function () {
    $response = $this->actingAs($this->user)->post('/admin/export-templates', [
        'name' => 'Bad Source',
        'format' => ExportFormat::Csv->value,
        'line_ending' => 'lf',
        'encoding' => 'utf-8',
        'file_extension' => 'csv',
        'columns' => [
            ['label' => 'X', 'source' => 'made_up_field', 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
        ],
    ]);

    $response->assertSessionHasErrors(['columns.0.source']);
});

it('updates an existing template + replaces its columns', function () {
    $template = ExportTemplate::where('slug', 'ledger-generic-csv')->firstOrFail();
    $originalColumnCount = $template->columns()->count();
    expect($originalColumnCount)->toBeGreaterThan(2);

    $response = $this->actingAs($this->user)->put("/admin/export-templates/{$template->slug}", [
        'name' => 'Renamed Generic',
        'format' => $template->format->value,
        'delimiter' => $template->delimiter,
        'quote_char' => $template->quote_char,
        'line_ending' => 'lf',
        'encoding' => 'utf-8',
        'include_header' => $template->include_header,
        'include_footer' => $template->include_footer,
        'is_default' => $template->is_default,
        'file_extension' => $template->file_extension,
        'columns' => [
            ['label' => 'Only', 'source' => ExportSource::ACCOUNT_CODE, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
        ],
    ]);

    $response->assertRedirect();
    $fresh = $template->fresh();
    expect($fresh->name)->toBe('Renamed Generic')
        ->and($fresh->columns()->count())->toBe(1)
        ->and($fresh->columns()->first()->label)->toBe('Only');
});

it('promotes a template to default and demotes the previous one', function () {
    $previousDefault = ExportTemplate::where('slug', 'ledger-generic-csv')->firstOrFail();
    $newDefault = ExportTemplate::where('slug', 'ledger-fixed-width-sample')->firstOrFail();

    $response = $this->actingAs($this->user)->post("/admin/export-templates/{$newDefault->slug}/default");

    $response->assertRedirect();
    expect($previousDefault->fresh()->is_default)->toBeFalse()
        ->and($newDefault->fresh()->is_default)->toBeTrue();
});

it('deletes a template with no batches', function () {
    $custom = ExportTemplate::factory()->create(['slug' => 'disposable']);
    ExportTemplateColumn::factory()->create(['export_template_id' => $custom->id]);

    $response = $this->actingAs($this->user)->delete("/admin/export-templates/{$custom->slug}");

    $response->assertRedirect();
    expect(ExportTemplate::find($custom->id))->toBeNull();
});

it('blocks deletion of a template referenced by a batch', function () {
    $template = ExportTemplate::where('slug', 'ledger-generic-csv')->firstOrFail();
    JournalEntry::post(['account_code' => '1010', 'description' => 'X', 'debit_cents' => 100_00]);
    $batch = LedgerExportBatch::query()->create([
        'period' => now()->format('Y-m'),
        'status' => 'ready',
        'entry_count' => 1,
        'debit_total_cents' => 10000,
        'credit_total_cents' => 0,
        'export_template_id' => $template->id,
    ]);

    $response = $this->actingAs($this->user)->delete("/admin/export-templates/{$template->slug}");

    $response->assertRedirect();
    expect(ExportTemplate::find($template->id))->not->toBeNull()
        ->and(LedgerExportBatch::find($batch->id))->not->toBeNull();
});

it('returns a JSON preview payload', function () {
    $response = $this->actingAs($this->user)->post('/admin/export-templates/preview', [
        'template' => [
            'name' => 'Preview',
            'format' => ExportFormat::Csv->value,
            'delimiter' => ',',
            'quote_char' => '"',
            'line_ending' => 'lf',
            'include_header' => true,
            'include_footer' => false,
            'file_extension' => 'csv',
            'columns' => [
                ['label' => 'Acct', 'source' => ExportSource::ACCOUNT_CODE, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
                ['label' => 'Amt', 'source' => ExportSource::DEBIT_CENTS, 'format_mask' => 'money:dot', 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
            ],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['payload']);
    expect($response->json('payload'))
        ->toContain('Acct,Amt')
        ->toContain('1010,2500.00');
});

it('returns a preview message when no columns are configured', function () {
    $response = $this->actingAs($this->user)->post('/admin/export-templates/preview', [
        'template' => ['columns' => []],
    ]);

    $response->assertStatus(200);
    expect($response->json('payload'))->toBe('(no columns configured)');
});

it('requires authentication for every endpoint', function () {
    $template = ExportTemplate::where('slug', 'ledger-generic-csv')->firstOrFail();

    $this->get('/admin/export-templates')->assertRedirect('/login');
    $this->get('/admin/export-templates/create')->assertRedirect('/login');
    $this->get("/admin/export-templates/{$template->slug}/edit")->assertRedirect('/login');
});
