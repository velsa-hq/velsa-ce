<?php

use App\Enums\WorkOrderKind;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Services\RecurringTemplateMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materializes a weekly template into work orders within the lookahead window', function () {
    $venue = Venue::factory()->create();
    $template = WorkOrderTemplate::query()->create([
        'venue_id' => $venue->id,
        'name' => 'Weekly check',
        'kind' => WorkOrderKind::PreventiveMaintenance->value,
        'recurrence_rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        'lookahead_days' => 14,
        'is_active' => true,
    ]);

    $count = app(RecurringTemplateMaterializer::class)->materializeTemplate($template);

    expect($count)->toBeGreaterThanOrEqual(1)
        ->and(WorkOrder::query()->where('template_id', $template->id)->count())->toBe($count)
        ->and($template->fresh()->last_materialized_at)->not->toBeNull();
});

it('is idempotent across repeated runs', function () {
    $venue = Venue::factory()->create();
    $template = WorkOrderTemplate::query()->create([
        'venue_id' => $venue->id,
        'name' => 'Daily',
        'kind' => WorkOrderKind::Cleaning->value,
        'recurrence_rrule' => 'FREQ=DAILY',
        'lookahead_days' => 7,
        'is_active' => true,
    ]);

    $first = app(RecurringTemplateMaterializer::class)->materializeTemplate($template);
    $second = app(RecurringTemplateMaterializer::class)->materializeTemplate($template);

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0)
        ->and(WorkOrder::query()->where('template_id', $template->id)->count())->toBe($first);
});

it('attaches items_json entries onto each work order', function () {
    $venue = Venue::factory()->create();
    $template = WorkOrderTemplate::query()->create([
        'venue_id' => $venue->id,
        'name' => 'Weekly with items',
        'kind' => WorkOrderKind::PreventiveMaintenance->value,
        'recurrence_rrule' => 'FREQ=WEEKLY',
        'items_json' => [
            ['sku' => 'FLT-01', 'name' => 'Filter', 'quantity' => 2, 'unit' => 'each', 'action' => 'consume'],
        ],
        'is_active' => true,
        'lookahead_days' => 7,
    ]);

    app(RecurringTemplateMaterializer::class)->materializeTemplate($template);

    $wo = WorkOrder::query()->where('template_id', $template->id)->first();
    expect($wo->items()->count())->toBe(1)
        ->and($wo->items()->first()->sku)->toBe('FLT-01');
});

it('skips inactive templates from materializeAll', function () {
    Venue::factory()->create();
    WorkOrderTemplate::query()->create([
        'venue_id' => Venue::query()->first()->id,
        'name' => 'Off',
        'kind' => WorkOrderKind::Repair->value,
        'recurrence_rrule' => 'FREQ=DAILY',
        'is_active' => false,
        'lookahead_days' => 7,
    ]);

    expect(app(RecurringTemplateMaterializer::class)->materializeAll())->toBe(0);
});

it('skips templates with no rrule', function () {
    $venue = Venue::factory()->create();
    $template = WorkOrderTemplate::query()->create([
        'venue_id' => $venue->id,
        'name' => 'No recurrence',
        'kind' => WorkOrderKind::Repair->value,
        'recurrence_rrule' => null,
        'is_active' => true,
        'lookahead_days' => 7,
    ]);

    expect(app(RecurringTemplateMaterializer::class)->materializeTemplate($template))->toBe(0);
});
