import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import ReportSchedulePanel from './report-schedule-panel';

type Column = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
};

type ParameterOption = { value: string | number; label: string };

type Parameter = {
    key: string;
    label: string;
    type: 'date' | 'month' | 'select' | 'text' | 'number';
    required?: boolean;
    default?: unknown;
    options?: ParameterOption[];
};

type SummaryEntry = {
    label: string;
    value: string;
    hint?: string | null;
};

type Result = {
    title: string;
    description: string;
    columns: Column[];
    rows: Array<Record<string, string | number | null>>;
    summary: SummaryEntry[];
    generated_at: string | null;
};

type Handler = {
    slug: string;
    title: string;
    category: string;
    description: string;
    parameters: Parameter[];
};

type ScheduleRow = {
    id: number;
    cadence: string;
    format: string;
    recipients: string[];
    is_active: boolean;
    last_run_at: string | null;
};

type Props = {
    handler: Handler;
    params: Record<string, string | number | null>;
    result: Result;
    can_schedule: boolean;
    schedules: ScheduleRow[];
};

const STATUS_BADGES: Record<string, string> = {
    inquiry:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    open: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    assigned: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    in_progress:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    new: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    qualified:
        'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    proposal_sent:
        'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    contract_sent:
        'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-100',
    won: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    lost: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    current:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    '30': 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    '60': 'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    '90': 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    replenish:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    monitor:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    YES: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

function renderCell(value: string | number | null): React.ReactNode {
    if (value === null || value === '') {
        return '-';
    }

    const stringValue = String(value);

    // Badge-ify if value matches a known status word.
    if (STATUS_BADGES[stringValue]) {
        return (
            <span
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_BADGES[stringValue]}`}
            >
                {stringValue.replace('_', ' ')}
            </span>
        );
    }

    return stringValue;
}

export default function ReportShow({
    handler,
    params,
    result,
    can_schedule,
    schedules,
}: Props) {
    const [draft, setDraft] = useState<Record<string, string>>(() => {
        const init: Record<string, string> = {};

        for (const key in params) {
            init[key] = params[key] === null ? '' : String(params[key]);
        }

        return init;
    });

    const applyFilters = () => {
        const cleaned: Record<string, string> = {};

        for (const [k, v] of Object.entries(draft)) {
            if (v !== '' && v !== null) {
                cleaned[k] = v;
            }
        }

        router.get(`/reports/${handler.slug}`, cleaned, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const exportQuery = new URLSearchParams(
        Object.entries(draft).filter(([, v]) => v !== '' && v !== null) as [
            string,
            string,
        ][],
    ).toString();
    const csvUrl = `/reports/${handler.slug}/export.csv?${exportQuery}`;
    const pdfUrl = `/reports/${handler.slug}/export.pdf?${exportQuery}`;
    const xlsxUrl = `/reports/${handler.slug}/export.xlsx?${exportQuery}`;

    return (
        <>
            <Head title={`${result.title} · Report`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <Link
                                href="/reports"
                                className="text-xs text-muted-foreground hover:underline"
                            >
                                Reports
                            </Link>
                            <span className="text-xs text-muted-foreground">
                                ·
                            </span>
                            <span className="text-xs font-medium text-muted-foreground">
                                {handler.category}
                            </span>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {result.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {result.description}
                        </p>
                        {result.generated_at ? (
                            <p className="text-[10px] text-muted-foreground">
                                Generated{' '}
                                {new Date(result.generated_at).toLocaleString()}
                            </p>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            data-tour-id="report-export-csv"
                        >
                            <a href={csvUrl}>Download CSV</a>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            data-tour-id="report-export-excel"
                        >
                            <a href={xlsxUrl}>Download Excel</a>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            data-tour-id="report-export-pdf"
                        >
                            <a href={pdfUrl} target="_blank" rel="noopener">
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </header>

                {result.summary.length > 0 ? (
                    <div
                        data-tour-id="report-summary"
                        className="grid gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6"
                    >
                        {result.summary.map((entry, idx) => (
                            <div
                                key={idx}
                                className="flex flex-col gap-0.5 rounded-lg border border-sidebar-border/70 p-2 text-xs dark:border-sidebar-border"
                            >
                                <span className="text-muted-foreground">
                                    {entry.label}
                                </span>
                                <span className="font-mono text-sm font-semibold">
                                    {entry.value}
                                </span>
                                {entry.hint ? (
                                    <span className="text-[10px] font-medium text-rose-700 dark:text-rose-300">
                                        {entry.hint}
                                    </span>
                                ) : null}
                            </div>
                        ))}
                    </div>
                ) : null}

                {handler.parameters.length > 0 ? (
                    <div
                        data-tour-id="report-filters"
                        className="flex flex-wrap items-end gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                    >
                        {handler.parameters.map((param) => (
                            <label
                                key={param.key}
                                className="flex flex-col gap-1 text-xs font-medium"
                            >
                                {param.label}
                                {param.type === 'select' ? (
                                    <select
                                        value={draft[param.key] ?? ''}
                                        onChange={(e) =>
                                            setDraft({
                                                ...draft,
                                                [param.key]: e.target.value,
                                            })
                                        }
                                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                                    >
                                        <option value="">Any</option>
                                        {(param.options ?? []).map((o) => (
                                            <option
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <input
                                        type={param.type}
                                        value={draft[param.key] ?? ''}
                                        onChange={(e) =>
                                            setDraft({
                                                ...draft,
                                                [param.key]: e.target.value,
                                            })
                                        }
                                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                                    />
                                )}
                            </label>
                        ))}
                        <Button
                            size="sm"
                            onClick={applyFilters}
                            data-tour-id="report-apply"
                        >
                            Apply
                        </Button>
                    </div>
                ) : null}

                <div
                    data-tour-id="report-table"
                    className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                {result.columns.map((col) => (
                                    <th
                                        key={col.key}
                                        className={`px-3 py-2 font-medium ${col.align === 'right' ? 'text-right' : 'text-left'}`}
                                    >
                                        {col.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {result.rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={result.columns.length}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No data matches these filters.
                                    </td>
                                </tr>
                            ) : (
                                result.rows.map((row, idx) => (
                                    <tr
                                        key={idx}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        {result.columns.map((col) => (
                                            <td
                                                key={col.key}
                                                className={`px-3 py-2 text-xs ${col.align === 'right' ? 'text-right font-mono' : ''}`}
                                            >
                                                {renderCell(
                                                    row[col.key] ?? null,
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {can_schedule ? (
                    <ReportSchedulePanel
                        slug={handler.slug}
                        schedules={schedules}
                        params={Object.fromEntries(
                            Object.entries(draft).filter(
                                ([, v]) => v !== '' && v !== null,
                            ),
                        )}
                    />
                ) : null}
            </div>
        </>
    );
}

ReportShow.layout = {
    breadcrumbs: [
        { title: 'Reports', href: '/reports' },
        { title: 'Report', href: '#' },
    ],
};
