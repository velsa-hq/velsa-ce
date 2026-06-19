<?php

use Illuminate\Console\Scheduling\Schedule;

// #[AsScheduledTask] is a no-op; these are registered in routes/console.php instead
it('schedules every nightly maintenance command', function () {
    $this->artisan('schedule:list')->assertExitCode(0);

    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '')
        ->implode("\n");

    foreach ([
        'pipeline:archive-stale',
        'invoices:advance-dunning',
        'installments:issue-due',
        'workorders:materialize',
    ] as $command) {
        expect($scheduled)->toContain($command);
    }
});
