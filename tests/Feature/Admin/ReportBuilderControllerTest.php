<?php

use App\Models\Booking;
use App\Models\ReportDefinition;
use App\Models\User;
use App\Reports\ReportDatasource;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists existing report definitions', function () {
    ReportDefinition::factory()->count(2)->create();

    $response = $this->actingAs($this->user)->get('/admin/report-builder');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/report-builder/index')
            ->has('definitions', 2)
        );
});

it('renders the create page with the full datasource catalog', function () {
    $response = $this->actingAs($this->user)->get('/admin/report-builder/create');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/report-builder/create')
            ->has('catalog')
            ->has('definition')
        );
});

it('stores a new definition', function () {
    $response = $this->actingAs($this->user)->post('/admin/report-builder', [
        'name' => 'Quarterly bookings by venue',
        'description' => 'Custom test',
        'datasource' => ReportDatasource::Bookings->value,
        'filters_json' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'definite'],
        ],
        'dimensions_json' => ['venue_name'],
        'metrics_json' => [
            ['field' => 'total_cents', 'aggregation' => 'sum', 'label' => 'Revenue'],
        ],
        'row_limit' => 500,
    ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect(ReportDefinition::where('name', 'Quarterly bookings by venue')->exists())
        ->toBeTrue();
});

it('rejects a definition with an unknown datasource', function () {
    $response = $this->actingAs($this->user)->post('/admin/report-builder', [
        'name' => 'Bad',
        'datasource' => 'martian_records',
    ]);

    $response->assertSessionHasErrors(['datasource']);
});

it('rejects a metric with a banned aggregation', function () {
    $response = $this->actingAs($this->user)->post('/admin/report-builder', [
        'name' => 'Banned agg',
        'datasource' => ReportDatasource::Bookings->value,
        'metrics_json' => [
            ['field' => 'total_cents', 'aggregation' => 'drop_table'],
        ],
    ]);

    $response->assertSessionHasErrors(['metrics_json.0.aggregation']);
});

it('runs the report and renders the show page with results', function () {
    Booking::factory()->count(3)->create();
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/admin/report-builder/{$def->slug}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/report-builder/show')
            ->has('result.columns')
            ->has('result.rows', 3)
        );
});

it('updates a definition', function () {
    $def = ReportDefinition::factory()->create([
        'name' => 'Old name',
        'datasource' => ReportDatasource::Bookings->value,
    ]);

    $response = $this->actingAs($this->user)->put("/admin/report-builder/{$def->slug}", [
        'name' => 'New name',
        'datasource' => $def->datasource->value,
        'description' => 'Updated',
    ]);

    $response->assertRedirect();
    expect($def->fresh()->name)->toBe('New name');
});

it('deletes a definition', function () {
    $def = ReportDefinition::factory()->create();

    $response = $this->actingAs($this->user)
        ->delete("/admin/report-builder/{$def->slug}");

    $response->assertRedirect();
    expect(ReportDefinition::find($def->id))->toBeNull();
});

it('requires authentication for every endpoint', function () {
    $def = ReportDefinition::factory()->create();

    $this->get('/admin/report-builder')->assertRedirect('/login');
    $this->get('/admin/report-builder/create')->assertRedirect('/login');
    $this->get("/admin/report-builder/{$def->slug}")->assertRedirect('/login');
});
