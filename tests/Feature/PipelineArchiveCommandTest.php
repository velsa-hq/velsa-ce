<?php

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('archives closed leads older than the configured window', function () {
    app(SystemSettings::class)->set('defaults.pipeline_archive_after_days', 60);

    $stale = Lead::factory()->atStage(LeadStage::Lost)->create([
        'closed_at' => now()->subDays(90),
        'expected_close_date' => now()->subDays(95)->toDateString(),
    ]);

    $this->artisan('pipeline:archive-stale')->assertSuccessful();

    expect($stale->fresh()->archived_at)->not->toBeNull();
});

it('keeps recently-closed leads on the board', function () {
    app(SystemSettings::class)->set('defaults.pipeline_archive_after_days', 60);

    $recent = Lead::factory()->atStage(LeadStage::Won)->create([
        'closed_at' => now()->subDays(10),
        'expected_close_date' => now()->subDays(10)->toDateString(),
    ]);

    $this->artisan('pipeline:archive-stale')->assertSuccessful();

    expect($recent->fresh()->archived_at)->toBeNull();
});

it('keeps future-dated closed leads on the board', function () {
    app(SystemSettings::class)->set('defaults.pipeline_archive_after_days', 60);

    $futureEvent = Lead::factory()->atStage(LeadStage::Won)->create([
        'closed_at' => now()->subDays(120),
        'expected_close_date' => now()->addMonths(3)->toDateString(),
    ]);

    $this->artisan('pipeline:archive-stale')->assertSuccessful();

    expect($futureEvent->fresh()->archived_at)->toBeNull();
});

it('never archives open leads', function () {
    app(SystemSettings::class)->set('defaults.pipeline_archive_after_days', 60);

    $open = Lead::factory()->atStage(LeadStage::Qualified)->create([
        'expected_close_date' => now()->subDays(200)->toDateString(),
    ]);

    $this->artisan('pipeline:archive-stale')->assertSuccessful();

    expect($open->fresh()->archived_at)->toBeNull();
});
