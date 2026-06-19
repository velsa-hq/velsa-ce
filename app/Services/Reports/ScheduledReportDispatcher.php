<?php

namespace App\Services\Reports;

use App\Mail\ScheduledReportMail;
use App\Models\ReportSchedule;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renders due report schedules and emails them. One failed schedule never
 * blocks the rest.
 */
class ScheduledReportDispatcher
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportRenderer $renderer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{dispatched: int, skipped: int, failed: int}
     */
    public function dispatchDue(CarbonImmutable $now): array
    {
        $dispatched = 0;
        $skipped = 0;
        $failed = 0;

        $schedules = ReportSchedule::query()->where('is_active', true)->get();

        foreach ($schedules as $schedule) {
            if (! $schedule->isDue($now)) {
                continue;
            }

            $handler = $this->registry->has($schedule->report_slug)
                ? $this->registry->get($schedule->report_slug)
                : null;

            if ($handler === null) {
                $skipped++;

                continue;
            }

            try {
                $params = $schedule->params_json ?? [];
                $result = $handler->run($params);
                $rendered = $this->renderer->render($handler, $result, $params, $schedule->format);

                Mail::to($schedule->recipients)->queue(new ScheduledReportMail(
                    schedule: $schedule,
                    reportTitle: $handler->title(),
                    filename: $rendered['filename'],
                    mime: $rendered['mime'],
                    body: $rendered['body'],
                ));

                // egress record for the report-by-email channel (NIST AC-4 / AU-12)
                $this->audit->record(
                    eventType: 'report_schedule.dispatched',
                    subject: $schedule,
                    payload: [
                        'report_slug' => $schedule->report_slug,
                        'recipients' => $schedule->recipients,
                        'format' => $schedule->format,
                    ],
                );

                $schedule->update(['last_run_at' => $now]);
                $dispatched++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped, 'failed' => $failed];
    }
}
