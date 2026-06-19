<?php

use App\Reports\Handlers\ArAgingReport;
use App\Reports\Handlers\BookedLocationsReport;
use App\Reports\Handlers\InventoryUtilizationReport;
use App\Reports\Handlers\SalesPipelineReport;
use App\Reports\Handlers\WorkOrderStatusReport;
use App\Reports\ReportRegistry;

it('registers all five named reports at boot time', function () {
    $registry = app(ReportRegistry::class);

    expect($registry->has('booked-locations'))->toBeTrue()
        ->and($registry->has('sales-pipeline'))->toBeTrue()
        ->and($registry->has('ar-aging'))->toBeTrue()
        ->and($registry->has('work-order-status'))->toBeTrue()
        ->and($registry->has('inventory-utilization'))->toBeTrue();
});

it('resolves a slug to the correct handler instance', function () {
    expect(app(ReportRegistry::class)->get('booked-locations'))
        ->toBeInstanceOf(BookedLocationsReport::class);
    expect(app(ReportRegistry::class)->get('sales-pipeline'))
        ->toBeInstanceOf(SalesPipelineReport::class);
    expect(app(ReportRegistry::class)->get('ar-aging'))
        ->toBeInstanceOf(ArAgingReport::class);
    expect(app(ReportRegistry::class)->get('work-order-status'))
        ->toBeInstanceOf(WorkOrderStatusReport::class);
    expect(app(ReportRegistry::class)->get('inventory-utilization'))
        ->toBeInstanceOf(InventoryUtilizationReport::class);
});

it('throws on unknown slugs', function () {
    expect(fn () => app(ReportRegistry::class)->get('does-not-exist'))
        ->toThrow(RuntimeException::class, 'Unknown report');
});

it('groups reports by category', function () {
    $groups = app(ReportRegistry::class)->grouped();

    expect(array_keys($groups))->toContain('Scheduling', 'Sales', 'Accounting', 'Operations');
});
