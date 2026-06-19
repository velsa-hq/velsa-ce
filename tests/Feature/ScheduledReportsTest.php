<?php

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reports\ScheduledReportDispatcher;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('creates a schedule from the report page, snapshotting filters', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->post('/reports/balance-sheet/schedules', [
        'format' => 'csv',
        'frequency' => 'weekly',
        'day_of_week' => 1,
        'hour' => 6,
        'recipients' => ['finance@county.gov'],
        'as_of' => '2026-06-01', // report filter, captured with the schedule
    ])->assertRedirect();

    $schedule = ReportSchedule::firstOrFail();

    expect($schedule->report_slug)->toBe('balance-sheet')
        ->and($schedule->frequency)->toBe('weekly')
        ->and($schedule->day_of_week)->toBe(1)
        ->and($schedule->recipients)->toBe(['finance@county.gov'])
        ->and($schedule->params_json['as_of'] ?? null)->toBe('2026-06-01');
});

it('rejects scheduling without the reports.schedule permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRoleAt(Venue::factory()->create(), 'sales_rep'); // lacks reports.schedule

    $this->actingAs($user)->post('/reports/balance-sheet/schedules', [
        'format' => 'csv',
        'frequency' => 'daily',
        'hour' => 6,
        'recipients' => ['x@y.test'],
    ])->assertForbidden();
});

it('dispatches a due schedule and emails it', function () {
    Mail::fake();
    $now = CarbonImmutable::create(2026, 6, 1, 6, 0, 0);
    $schedule = ReportSchedule::factory()->create([
        'report_slug' => 'balance-sheet',
        'format' => 'csv',
        'frequency' => 'daily',
        'hour' => 6,
        'last_run_at' => null,
    ]);

    $result = app(ScheduledReportDispatcher::class)->dispatchDue($now);

    expect($result['dispatched'])->toBe(1)
        ->and($schedule->refresh()->last_run_at)->not->toBeNull();

    Mail::assertQueued(ScheduledReportMail::class);
});

it('skips a schedule outside its scheduled hour', function () {
    Mail::fake();
    $now = CarbonImmutable::create(2026, 6, 1, 9, 0, 0);
    ReportSchedule::factory()->create(['format' => 'csv', 'frequency' => 'daily', 'hour' => 6]);

    expect(app(ScheduledReportDispatcher::class)->dispatchDue($now)['dispatched'])->toBe(0);
    Mail::assertNothingQueued();
});

it('does not send a daily schedule twice in one day', function () {
    Mail::fake();
    $now = CarbonImmutable::create(2026, 6, 1, 6, 0, 0);
    ReportSchedule::factory()->create([
        'format' => 'csv',
        'frequency' => 'daily',
        'hour' => 6,
        'last_run_at' => $now->subHour(), // already ran today
    ]);

    expect(app(ScheduledReportDispatcher::class)->dispatchDue($now)['dispatched'])->toBe(0);
});

it('removes a schedule', function () {
    $admin = grantSuperAdmin();
    $schedule = ReportSchedule::factory()->create(['report_slug' => 'balance-sheet']);

    $this->actingAs($admin)->delete("/reports/balance-sheet/schedules/{$schedule->id}")->assertRedirect();

    expect(ReportSchedule::count())->toBe(0);
});

it('runs as the scheduled command', function () {
    $this->artisan('reports:dispatch-scheduled')->assertExitCode(0);
});
