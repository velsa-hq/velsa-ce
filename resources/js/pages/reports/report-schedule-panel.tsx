import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Schedule = {
    id: number;
    cadence: string;
    format: string;
    recipients: string[];
    is_active: boolean;
    last_run_at: string | null;
};

type Props = {
    slug: string;
    schedules: Schedule[];
    params: Record<string, string>;
};

const selectClass =
    'rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border';

const DAYS = [
    'Sunday',
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
];

export default function ReportSchedulePanel({
    slug,
    schedules,
    params,
}: Props) {
    const [open, setOpen] = useState(false);
    const [recipients, setRecipients] = useState('');

    const form = useForm<{
        frequency: string;
        day_of_week: number;
        day_of_month: number;
        hour: number;
        format: string;
        recipients: string[];
    }>({
        frequency: 'weekly',
        day_of_week: 1,
        day_of_month: 1,
        hour: 6,
        format: 'pdf',
        recipients: [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const emails = recipients
            .split(/[,\n]/)
            .map((r) => r.trim())
            .filter(Boolean);

        router.post(
            `/reports/${slug}/schedules`,
            { ...params, ...form.data, recipients: emails },
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset();
                    setRecipients('');
                    setOpen(false);
                },
            },
        );
    };

    const remove = (id: number) => {
        if (!window.confirm('Remove this schedule?')) {
            return;
        }

        router.delete(`/reports/${slug}/schedules/${id}`, {
            preserveScroll: true,
        });
    };

    return (
        <section className="rounded-lg border border-sidebar-border/40 dark:border-sidebar-border/60">
            <div className="flex items-center justify-between px-3 py-2">
                <h2 className="text-sm font-semibold">Scheduled delivery</h2>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen((v) => !v)}
                    data-tour-id="report-schedule"
                >
                    {open ? 'Cancel' : '+ Schedule this report'}
                </Button>
            </div>

            {schedules.length > 0 && (
                <ul className="divide-y divide-sidebar-border/40 border-t border-sidebar-border/40 text-sm dark:divide-sidebar-border/60 dark:border-sidebar-border/60">
                    {schedules.map((s) => (
                        <li
                            key={s.id}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div className="flex flex-col">
                                <span className="font-medium">
                                    {s.cadence} · {s.format.toUpperCase()}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {s.recipients.join(', ')}
                                    {s.last_run_at
                                        ? ` · last sent ${s.last_run_at}`
                                        : ' · never sent'}
                                </span>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => remove(s.id)}
                            >
                                Remove
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {open && (
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-3 border-t border-sidebar-border/40 p-3 dark:border-sidebar-border/60"
                >
                    <p className="text-xs text-muted-foreground">
                        The report's current filters are saved with the schedule
                        and re-applied each run.
                    </p>

                    <div className="flex flex-wrap items-end gap-3">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">Frequency</span>
                            <select
                                className={selectClass}
                                value={form.data.frequency}
                                onChange={(e) =>
                                    form.setData('frequency', e.target.value)
                                }
                            >
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </label>

                        {form.data.frequency === 'weekly' && (
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="font-medium">Day</span>
                                <select
                                    className={selectClass}
                                    value={form.data.day_of_week}
                                    onChange={(e) =>
                                        form.setData(
                                            'day_of_week',
                                            Number(e.target.value),
                                        )
                                    }
                                >
                                    {DAYS.map((d, i) => (
                                        <option key={d} value={i}>
                                            {d}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}

                        {form.data.frequency === 'monthly' && (
                            <label className="flex flex-col gap-1 text-sm">
                                <span className="font-medium">
                                    Day of month
                                </span>
                                <Input
                                    type="number"
                                    min={1}
                                    max={28}
                                    className="w-20"
                                    value={form.data.day_of_month}
                                    onChange={(e) =>
                                        form.setData(
                                            'day_of_month',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </label>
                        )}

                        <label className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">Hour</span>
                            <select
                                className={selectClass}
                                value={form.data.hour}
                                onChange={(e) =>
                                    form.setData('hour', Number(e.target.value))
                                }
                            >
                                {Array.from({ length: 24 }, (_, h) => (
                                    <option key={h} value={h}>
                                        {String(h).padStart(2, '0')}:00
                                    </option>
                                ))}
                            </select>
                        </label>

                        <label className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">Format</span>
                            <select
                                className={selectClass}
                                value={form.data.format}
                                onChange={(e) =>
                                    form.setData('format', e.target.value)
                                }
                            >
                                <option value="pdf">PDF</option>
                                <option value="xlsx">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </label>
                    </div>

                    <label className="flex flex-col gap-1 text-sm">
                        <span className="font-medium">Recipients</span>
                        <Input
                            type="text"
                            placeholder="finance@county.gov, director@county.gov"
                            value={recipients}
                            onChange={(e) => setRecipients(e.target.value)}
                        />
                        <span className="text-xs text-muted-foreground">
                            Comma-separated email addresses.
                        </span>
                    </label>

                    <div>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || !recipients.trim()}
                        >
                            Create schedule
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}
