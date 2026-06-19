import { Head, Link, useForm } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Kind = {
    key: string;
    label: string;
    description: string;
    requires_read_only: boolean;
};

type JobRow = {
    id: number;
    kind: string;
    kind_label: string;
    status: string;
    status_label: string;
    original_filename: string;
    total_rows: number;
    created_rows: number;
    error_rows: number;
    created_by: string | null;
    created_at: string | null;
};

type Props = {
    kinds: Kind[];
    jobs: JobRow[];
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

export default function ImportsIndex({ kinds, jobs }: Props) {
    const form = useForm<{
        kind: string;
        file: File | null;
        has_header: boolean;
        delimiter: string;
    }>({
        kind: kinds[0]?.key ?? '',
        file: null,
        has_header: true,
        delimiter: ',',
    });

    const selectedKind = kinds.find((k) => k.key === form.data.kind);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/imports', { forceFormData: true });
    };

    return (
        <>
            <Head title="Data import · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        Data import
                        <HelpLink slug="admin/data-import" />
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Bring records in from a CSV - upload, map your columns
                        to Velsa fields, preview, then commit. Nothing is
                        written until you confirm, and a recent import can be
                        reversed.
                    </p>
                </header>

                <form
                    onSubmit={submit}
                    className="max-w-2xl rounded-lg border border-border bg-card p-4"
                >
                    <h2 className="mb-3 text-sm font-semibold">New import</h2>

                    <div className="flex flex-col gap-3">
                        <label className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">Record type</span>
                            <select
                                data-tour-id="import-kind"
                                className={selectClass}
                                value={form.data.kind}
                                onChange={(e) =>
                                    form.setData('kind', e.target.value)
                                }
                            >
                                {kinds.map((k) => (
                                    <option key={k.key} value={k.key}>
                                        {k.label}
                                    </option>
                                ))}
                            </select>
                            {selectedKind && (
                                <span className="text-xs text-muted-foreground">
                                    {selectedKind.description}
                                    {selectedKind.requires_read_only &&
                                        ' Requires read-only mode to commit.'}
                                </span>
                            )}
                        </label>

                        <label className="flex flex-col gap-1 text-sm">
                            <span className="font-medium">CSV file</span>
                            <Input
                                data-tour-id="import-upload"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                onChange={(e) =>
                                    form.setData(
                                        'file',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            {form.errors.file && (
                                <span className="text-xs text-destructive">
                                    {form.errors.file}
                                </span>
                            )}
                        </label>

                        <div className="flex flex-wrap items-center gap-4">
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.has_header}
                                    onChange={(e) =>
                                        form.setData(
                                            'has_header',
                                            e.target.checked,
                                        )
                                    }
                                />
                                First row is a header
                            </label>

                            <label className="flex items-center gap-2 text-sm">
                                Delimiter
                                <select
                                    className={selectClass}
                                    value={form.data.delimiter}
                                    onChange={(e) =>
                                        form.setData(
                                            'delimiter',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value=",">Comma</option>
                                    <option value=";">Semicolon</option>
                                    <option value="|">Pipe</option>
                                    <option value="tab">Tab</option>
                                </select>
                            </label>
                        </div>

                        <div>
                            <Button
                                type="submit"
                                disabled={!form.data.file || form.processing}
                            >
                                Upload &amp; map
                            </Button>
                        </div>
                    </div>
                </form>

                <section className="flex flex-col gap-2">
                    <h2 className="text-sm font-semibold">Recent imports</h2>
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2">File</th>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Rows</th>
                                    <th className="px-3 py-2">By</th>
                                    <th className="px-3 py-2">When</th>
                                </tr>
                            </thead>
                            <tbody>
                                {jobs.map((job) => (
                                    <tr
                                        key={job.id}
                                        className="border-t border-border hover:bg-muted/30"
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/admin/imports/${job.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {job.original_filename}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            {job.kind_label}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={
                                                    statusVariant[job.status] ??
                                                    'outline'
                                                }
                                            >
                                                {job.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {job.status === 'completed'
                                                ? `${job.created_rows} created`
                                                : job.total_rows > 0
                                                  ? `${job.total_rows} read`
                                                  : '-'}
                                            {job.error_rows > 0 && (
                                                <span className="text-destructive">
                                                    {' '}
                                                    · {job.error_rows} errors
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {job.created_by ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {job.created_at ?? '-'}
                                        </td>
                                    </tr>
                                ))}
                                {jobs.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-4 py-6 text-center text-sm text-muted-foreground"
                                        >
                                            No imports yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/imports' },
        { title: 'Data import', href: '/admin/imports' },
    ],
};
