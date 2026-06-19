import { Head, Link, router, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Field = { key: string; label: string; required: boolean; hint: string };

type Job = {
    id: number;
    kind: string;
    kind_label: string;
    status: string;
    status_label: string;
    original_filename: string;
    has_header: boolean;
    delimiter: string;
    column_map: Record<string, string | null>;
    total_rows: number;
    valid_rows: number;
    created_rows: number;
    error_rows: number;
    is_reversible: boolean;
    previewed_at: string | null;
    committed_at: string | null;
    reversed_at: string | null;
};

type ImportError = {
    row_number: number;
    field: string | null;
    message: string;
};

type Props = {
    job: Job;
    fields: Field[];
    headers: string[];
    requires_read_only: boolean;
    errors_sample: ImportError[];
};

const selectClass =
    'rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border';

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    previewed: 'secondary',
    completed: 'default',
    failed: 'destructive',
    reversed: 'outline',
};

export default function ImportShow({
    job,
    fields,
    headers,
    requires_read_only,
    errors_sample,
}: Props) {
    const terminal = ['completed', 'failed', 'reversed'].includes(job.status);

    const form = useForm<{ column_map: Record<string, string | null> }>({
        column_map: job.column_map ?? {},
    });

    const setMap = (key: string, value: string) =>
        form.setData('column_map', {
            ...form.data.column_map,
            [key]: value === '' ? null : value,
        });

    const preview = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/admin/imports/${job.id}/preview`, { preserveScroll: true });
    };

    const commit = () =>
        router.post(
            `/admin/imports/${job.id}/commit`,
            {},
            { preserveScroll: true },
        );

    const reverse = () => {
        if (
            !window.confirm(
                'Reverse this import? Records it created will be deleted.',
            )
        ) {
            return;
        }

        router.post(
            `/admin/imports/${job.id}/reverse`,
            {},
            { preserveScroll: true },
        );
    };

    const destroy = () => {
        if (!window.confirm('Delete this import job and its uploaded file?')) {
            return;
        }

        router.delete(`/admin/imports/${job.id}`);
    };

    const canCommit = job.status === 'previewed' && job.valid_rows > 0;

    return (
        <>
            <Head title={`${job.original_filename} · Import`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href="/admin/imports"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            All imports
                        </Link>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            {job.original_filename}
                            <Badge
                                variant={statusVariant[job.status] ?? 'outline'}
                            >
                                {job.status_label}
                            </Badge>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {job.kind_label} · delimiter "
                            {job.delimiter === '\t' ? 'tab' : job.delimiter}" ·{' '}
                            {job.has_header ? 'has header' : 'no header'}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" onClick={destroy}>
                        Delete
                    </Button>
                </header>

                {requires_read_only && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                        This record type changes the live dataset - enable
                        read-only mode (Admin &gt; System settings) before
                        committing. Commit is blocked until you do.
                    </div>
                )}

                {/* Summary */}
                {job.total_rows > 0 && (
                    <section className="flex flex-wrap gap-4 rounded-lg border border-border bg-card p-4 text-sm">
                        <Stat label="Rows read" value={job.total_rows} />
                        {terminal ? (
                            <Stat
                                label="Created"
                                value={job.created_rows}
                                tone="good"
                            />
                        ) : (
                            <Stat
                                label="Valid"
                                value={job.valid_rows}
                                tone="good"
                            />
                        )}
                        <Stat
                            label="Errors"
                            value={job.error_rows}
                            tone={job.error_rows > 0 ? 'bad' : undefined}
                        />
                        {job.error_rows > 0 && (
                            <a
                                data-tour-id="import-errors"
                                href={`/admin/imports/${job.id}/errors`}
                                className="self-center text-sm text-primary hover:underline"
                            >
                                Download error report
                            </a>
                        )}
                    </section>
                )}

                {/* Mapping */}
                <form onSubmit={preview} className="flex flex-col gap-3">
                    <h2 className="text-sm font-semibold">Map columns</h2>
                    <div
                        data-tour-id="import-map"
                        className="overflow-x-auto rounded-lg border border-border"
                    >
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2">Velsa field</th>
                                    <th className="px-3 py-2">Source column</th>
                                    <th className="px-3 py-2">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                {fields.map((field) => (
                                    <tr
                                        key={field.key}
                                        className="border-t border-border"
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            {field.label}
                                            {field.required && (
                                                <span className="text-destructive">
                                                    {' '}
                                                    *
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <select
                                                className={selectClass}
                                                disabled={terminal}
                                                value={
                                                    form.data.column_map[
                                                        field.key
                                                    ] ?? ''
                                                }
                                                onChange={(e) =>
                                                    setMap(
                                                        field.key,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    - skip -
                                                </option>
                                                {headers.map((h) => (
                                                    <option key={h} value={h}>
                                                        {h}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {field.hint}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {form.errors.column_map && (
                        <p className="text-sm text-destructive">
                            {form.errors.column_map}
                        </p>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        {!terminal && (
                            <Button
                                data-tour-id="import-preview"
                                type="submit"
                                variant="secondary"
                                disabled={form.processing}
                            >
                                Preview (dry run)
                            </Button>
                        )}
                        {!terminal && (
                            <Button
                                data-tour-id="import-commit"
                                type="button"
                                onClick={commit}
                                disabled={!canCommit}
                                title={
                                    canCommit
                                        ? undefined
                                        : 'Preview first; needs at least one valid row.'
                                }
                            >
                                Commit import
                            </Button>
                        )}
                        {job.is_reversible && (
                            <Button
                                data-tour-id="import-reverse"
                                type="button"
                                variant="destructive"
                                onClick={reverse}
                            >
                                Reverse import
                            </Button>
                        )}
                    </div>
                </form>

                {/* Error sample */}
                {errors_sample.length > 0 && (
                    <section className="flex flex-col gap-2">
                        <h2 className="text-sm font-semibold">
                            Errors{' '}
                            <span className="font-normal text-muted-foreground">
                                (showing {errors_sample.length} of{' '}
                                {job.error_rows})
                            </span>
                        </h2>
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                    <tr>
                                        <th className="px-3 py-2">Row</th>
                                        <th className="px-3 py-2">Field</th>
                                        <th className="px-3 py-2">Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {errors_sample.map((err, i) => (
                                        <tr
                                            key={i}
                                            className="border-t border-border"
                                        >
                                            <td className="px-3 py-2 tabular-nums">
                                                {err.row_number}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {err.field ?? '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {err.message}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'good' | 'bad';
}) {
    const color =
        tone === 'good'
            ? 'text-emerald-600 dark:text-emerald-400'
            : tone === 'bad'
              ? 'text-destructive'
              : 'text-foreground';

    return (
        <div className="flex flex-col">
            <span className={`text-xl font-semibold tabular-nums ${color}`}>
                {value}
            </span>
            <span className="text-xs text-muted-foreground">{label}</span>
        </div>
    );
}

ImportShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/imports' },
        { title: 'Data import', href: '/admin/imports' },
    ],
};
