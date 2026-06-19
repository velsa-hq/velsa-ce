<?php

use App\Dashboard\DashboardTileRegistry;
use App\Dashboard\Tiles\NeedsAttentionTile;
use App\Enums\LeadStage;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Services\Pipeline\PipelineStageConfig;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

// ---------- Stage config service ----------

it('falls back to enum defaults when no overrides are stored', function () {
    $config = app(PipelineStageConfig::class);

    expect($config->label(LeadStage::ProposalSent))->toBe('Proposal sent')
        ->and($config->probability(LeadStage::Qualified))
        ->toBe(LeadStage::Qualified->defaultProbability());
});

it('applies stored label and probability overrides', function () {
    $config = app(PipelineStageConfig::class);
    $config->save([
        'qualified' => ['label' => 'Vetted', 'probability' => 0.4],
    ]);

    $fresh = app(PipelineStageConfig::class);
    expect($fresh->label(LeadStage::Qualified))->toBe('Vetted')
        ->and($fresh->probability(LeadStage::Qualified))->toBe(0.4);
});

it('keeps terminal-stage probabilities fixed regardless of overrides', function () {
    $config = app(PipelineStageConfig::class);
    $config->save([
        'won' => ['label' => 'Booked', 'probability' => 0.5],
        'lost' => ['label' => 'Dead', 'probability' => 0.9],
    ]);

    $fresh = app(PipelineStageConfig::class);
    expect($fresh->probability(LeadStage::Won))->toBe(1.0)
        ->and($fresh->probability(LeadStage::Lost))->toBe(0.0)
        // labels still apply to terminal stages
        ->and($fresh->label(LeadStage::Won))->toBe('Booked');
});

it('uses the configured probability when creating a new opportunity', function () {
    app(PipelineStageConfig::class)->save([
        'qualified' => ['probability' => 0.42],
    ]);
    $client = Client::factory()->create();

    $this->actingAs($this->user)->post('/leads', [
        'client_id' => $client->id,
        'name' => 'Configured prob',
        'stage' => 'qualified',
    ])->assertRedirect();

    expect(Lead::query()->where('name', 'Configured prob')->value('probability'))
        ->toBe(0.42);
});

it('shows configured stage labels on the pipeline board', function () {
    app(PipelineStageConfig::class)->save([
        'new' => ['label' => 'Fresh inquiry'],
    ]);

    $this->actingAs($this->user)
        ->get('/pipeline')
        ->assertInertia(fn ($page) => $page
            ->where('columns', fn ($columns) => collect($columns)
                ->firstWhere('key', 'new')['label'] === 'Fresh inquiry'));
});

// ---------- Stage editor admin page ----------

it('renders the pipeline-stage editor for an admin', function () {
    $this->actingAs($this->user)
        ->get('/admin/pipeline-stages')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/pipeline-stages/index')
            ->has('stages', 6));
});

it('saves stage overrides via the editor endpoint', function () {
    $this->actingAs($this->user)->put('/admin/pipeline-stages', [
        'stages' => [
            'new' => ['label' => 'Lead in', 'probability' => 0.15],
            'qualified' => ['label' => 'Qualified', 'probability' => 0.3],
        ],
    ])->assertRedirect();

    $config = app(PipelineStageConfig::class);
    expect($config->label(LeadStage::New))->toBe('Lead in')
        ->and($config->probability(LeadStage::New))->toBe(0.15);
});

it('rejects an out-of-range probability', function () {
    $this->actingAs($this->user)->put('/admin/pipeline-stages', [
        'stages' => [
            'new' => ['label' => 'New', 'probability' => 2],
        ],
    ])->assertSessionHasErrors('stages.new.probability');
});

// ---------- Overdue grace window ----------

it('honors the overdue grace window on the board', function () {
    // enum order fixes Qualified at column index 1
    Lead::factory()->atStage(LeadStage::Qualified)->create([
        'name' => 'Slightly late',
        'expected_close_date' => now()->subDays(3)->toDateString(),
    ]);

    // grace of 5 days -> a 3-day-late lead is not yet overdue
    app(SystemSettings::class)->set('defaults.pipeline_overdue_grace_days', 5);
    $this->actingAs($this->user)->get('/pipeline')->assertInertia(
        fn ($page) => $page->where('columns.1.leads.0.is_overdue', false),
    );

    // grace of 0 -> it is overdue
    app(SystemSettings::class)->set('defaults.pipeline_overdue_grace_days', 0);
    $this->actingAs($this->user)->get('/pipeline')->assertInertia(
        fn ($page) => $page->where('columns.1.leads.0.is_overdue', true),
    );
});

// ---------- Default new-user dashboard tiles ----------

it('drives the default tile set from the setting and drops unknown keys', function () {
    app(SystemSettings::class)->set(
        'defaults.dashboard_default_tiles',
        'quick_links,not_a_real_tile,kpi_strip',
    );

    expect(app(DashboardTileRegistry::class)->defaultTileKeys())
        ->toBe(['quick_links', 'kpi_strip']);
});

it('falls back to the built-in tiles when the setting is empty', function () {
    expect(app(DashboardTileRegistry::class)->defaultTileKeys())
        ->toBe(DashboardTileRegistry::BUILTIN_DEFAULT_TILES);
});

// ---------- Needs-attention thresholds ----------

it('reflects the configured stale-window in the needs-attention tile copy', function () {
    app(SystemSettings::class)->set('defaults.needs_attention_lead_stuck_days', 30);

    $tile = app(NeedsAttentionTile::class);
    $render = $tile->render($this->user);
    $stuck = collect($render['groups'])->firstWhere('key', 'stuck_leads');

    expect($stuck['unit'])->toBe('no movement 30d+');
});
