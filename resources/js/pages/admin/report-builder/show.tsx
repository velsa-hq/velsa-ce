import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Column = { key: string; label: string; align?: string };

type Result = {
    columns: Column[];
    rows: Record<string, unknown>[];
    summary: { label: string; value: string }[];
    generated_at: string | null;
};

type Definition = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    datasource: string;
    datasource_label: string | null;
    filters_json: { field: string; operator: string; value: string }[];
    dimensions_json: string[];
    metrics_json: { field: string; aggregation: string; label?: string }[];
    row_limit: number;
};

type Props = {
    definition: Definition;
    result: Result | null;
    error: string | null;
};

export default function ReportBuilderShow({
    definition,
    result,
    error,
}: Props) {
    return (
        <>
            <Head title={definition.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {definition.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {definition.description}
                        </p>
                        <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline">
                                {definition.datasource_label}
                            </Badge>
                            <span>
                                {definition.filters_json.length} filter
                                {definition.filters_json.length === 1
                                    ? ''
                                    : 's'}
                            </span>
                            <span>·</span>
                            <span>
                                {definition.dimensions_json.length} dimension
                                {definition.dimensions_json.length === 1
                                    ? ''
                                    : 's'}
                            </span>
                            <span>·</span>
                            <span>
                                {definition.metrics_json.length} metric
                                {definition.metrics_json.length === 1
                                    ? ''
                                    : 's'}
                            </span>
                            <span>·</span>
                            <span>limit {definition.row_limit}</span>
                        </div>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={`/admin/report-builder/${definition.slug}/edit`}
                        >
                            <Pencil className="size-4" /> Edit
                        </Link>
                    </Button>
                </header>

                {error && (
                    <div
                        role="alert"
                        className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        Report failed: {error}
                    </div>
                )}

                {result && (
                    <>
                        <div className="flex flex-wrap gap-3">
                            {result.summary.map((s) => (
                                <div
                                    key={s.label}
                                    className="rounded-lg border border-border bg-card px-3 py-2"
                                >
                                    <div className="text-xs tracking-wider text-muted-foreground uppercase">
                                        {s.label}
                                    </div>
                                    <div className="font-semibold tabular-nums">
                                        {s.value}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                            <div className="max-h-[60vh] overflow-auto">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted/80 backdrop-blur">
                                        <tr>
                                            {result.columns.map((c) => (
                                                <th
                                                    key={c.key}
                                                    className={`px-3 py-2 font-medium ${
                                                        c.align === 'right'
                                                            ? 'text-right'
                                                            : 'text-left'
                                                    }`}
                                                >
                                                    {c.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {result.rows.map((row, i) => (
                                            <tr
                                                key={i}
                                                className="border-t border-border/60 hover:bg-muted/30"
                                            >
                                                {result.columns.map((c) => (
                                                    <td
                                                        key={c.key}
                                                        className={`px-3 py-2 ${
                                                            c.align === 'right'
                                                                ? 'text-right tabular-nums'
                                                                : ''
                                                        }`}
                                                    >
                                                        {String(
                                                            row[c.key] ?? '-',
                                                        )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                        {result.rows.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={
                                                        result.columns.length
                                                    }
                                                    className="px-3 py-6 text-center text-sm text-muted-foreground"
                                                >
                                                    No rows match this
                                                    definition.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ReportBuilderShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/report-builder' },
        { title: 'Report builder', href: '/admin/report-builder' },
        { title: 'Result', href: '#' },
    ],
};
