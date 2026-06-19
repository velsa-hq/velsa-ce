<?php

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a WO- prefixed reference on create', function () {
    $wo = WorkOrder::factory()->create(['reference' => null]);

    expect($wo->reference)->toStartWith('WO-');
});

it('casts status + kind to their respective enums', function () {
    $wo = WorkOrder::factory()->withStatus(WorkOrderStatus::Completed)->create();

    expect($wo->status)->toBe(WorkOrderStatus::Completed);
});

it('auto-sets completed_at on transition to Completed', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::Open->value, 'completed_at' => null]);

    $wo->update(['status' => WorkOrderStatus::Completed->value]);

    expect($wo->completed_at)->not->toBeNull();
});

it('clears completed_at when reopening', function () {
    $wo = WorkOrder::factory()->withStatus(WorkOrderStatus::Completed)->create();
    expect($wo->completed_at)->not->toBeNull();

    $wo->update(['status' => WorkOrderStatus::InProgress->value]);

    expect($wo->fresh()->completed_at)->toBeNull();
});

it('scopes ->open() to non-terminal statuses', function () {
    WorkOrder::factory()->count(2)->create(['status' => WorkOrderStatus::Open->value]);
    WorkOrder::factory()->create(['status' => WorkOrderStatus::Completed->value]);
    WorkOrder::factory()->create(['status' => WorkOrderStatus::Cancelled->value]);

    expect(WorkOrder::query()->open()->count())->toBe(2);
});

it('scopes ->overdue() to open work orders with past scheduled_for', function () {
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Open->value,
        'scheduled_for' => now()->subDays(2),
    ]);
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Open->value,
        'scheduled_for' => now()->addDays(2),
    ]);
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Completed->value,
        'scheduled_for' => now()->subDays(2),
    ]);

    expect(WorkOrder::query()->overdue()->count())->toBe(1);
});

it('isOverdue() returns true for past-scheduled open work orders', function () {
    $wo = WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Open->value,
        'scheduled_for' => now()->subHour(),
    ]);

    expect($wo->isOverdue())->toBeTrue();
});

it('isOverdue() returns false for completed work orders', function () {
    $wo = WorkOrder::factory()->withStatus(WorkOrderStatus::Completed)->create([
        'scheduled_for' => now()->subDay(),
    ]);

    expect($wo->isOverdue())->toBeFalse();
});
