import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Definition = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    datasource: string | null;
    datasource_label: string | null;
    created_by: string | null;
    updated_at: string | null;
    runs_count: number;
};

type Props = { definitions: Definition[] };

export default function ReportBuilderIndex({ definitions }: Props) {
    const remove = (def: Definition) => {
        if (!confirm(`Delete "${def.name}"?`)) {
            return;
        }

        router.delete(`/admin/report-builder/${def.slug}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Report builder · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Report builder
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            User-defined reports. Pick a datasource, apply
                            filters, group by dimensions, choose aggregations.
                            Saved definitions appear in the main Reports list
                            alongside the canned ones.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/report-builder/create">
                            <Plus className="size-4" /> New report
                        </Link>
                    </Button>
                </header>

                {definitions.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        No user-defined reports yet. Click "New report" to build
                        one.
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Name
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Datasource
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Created by
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Runs
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {definitions.map((d) => (
                                    <tr
                                        key={d.id}
                                        className="border-t border-border/60 hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/admin/report-builder/${d.slug}`}
                                                className="font-medium hover:underline"
                                            >
                                                {d.name}
                                            </Link>
                                            {d.description && (
                                                <p className="mt-0.5 max-w-md text-xs text-muted-foreground">
                                                    {d.description}
                                                </p>
                                            )}
                                            <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                                {d.slug}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {d.datasource_label ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {d.created_by ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {d.runs_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                    aria-label={`Run ${d.name}`}
                                                >
                                                    <Link
                                                        href={`/admin/report-builder/${d.slug}`}
                                                    >
                                                        <Play className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                    aria-label={`Edit ${d.name}`}
                                                >
                                                    <Link
                                                        href={`/admin/report-builder/${d.slug}/edit`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() => remove(d)}
                                                    aria-label={`Delete ${d.name}`}
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

ReportBuilderIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/report-builder' },
        { title: 'Report builder', href: '/admin/report-builder' },
    ],
};
