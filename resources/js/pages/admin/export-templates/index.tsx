import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Star, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create,
    edit,
    setDefault,
    destroy,
} from '@/routes/admin/export-templates';

type TemplateRow = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    format: string | null;
    format_label: string | null;
    is_default: boolean;
    file_extension: string;
    column_count: number;
    batch_count: number;
    updated_at: string | null;
};

type Props = {
    templates: TemplateRow[];
};

function relTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    const d = new Date(iso);
    const days = Math.floor((Date.now() - d.getTime()) / 86_400_000);

    if (days < 1) {
        return 'today';
    }

    if (days === 1) {
        return 'yesterday';
    }

    if (days < 30) {
        return `${days}d ago`;
    }

    return d.toLocaleDateString();
}

export default function ExportTemplatesIndex({ templates }: Props) {
    const handleSetDefault = (slug: string) => {
        router.post(
            setDefault({ template: slug }).url,
            {},
            { preserveScroll: true },
        );
    };

    const handleDelete = (row: TemplateRow) => {
        if (
            !confirm(
                `Delete "${row.name}"?\n\nThis cannot be undone. If any historical export batches reference this template the delete will be blocked.`,
            )
        ) {
            return;
        }

        router.delete(destroy({ template: row.slug }).url, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Export templates · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Export templates
                        </h1>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Configurable journal-export shapes used by the GL
                            exporter. Mark one template as the default; the
                            exporter uses it when no specific template is chosen
                            at run time.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create().url}>New template</Link>
                    </Button>
                </header>

                {templates.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        No export templates yet. Create one to enable GL
                        exports.
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
                                        Format
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Columns
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Batches
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        Updated
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {templates.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-t border-border/60 hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={
                                                        edit({
                                                            template: row.slug,
                                                        }).url
                                                    }
                                                    className="font-medium text-foreground hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                                {row.is_default && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="gap-1"
                                                    >
                                                        <Star className="size-3" />
                                                        Default
                                                    </Badge>
                                                )}
                                            </div>
                                            {row.description && (
                                                <p className="mt-1 max-w-md text-xs text-muted-foreground">
                                                    {row.description}
                                                </p>
                                            )}
                                            <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                {row.slug} · .
                                                {row.file_extension}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {row.format_label ??
                                                row.format ??
                                                '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {row.column_count}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {row.batch_count}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {relTime(row.updated_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {!row.is_default && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            handleSetDefault(
                                                                row.slug,
                                                            )
                                                        }
                                                        aria-label={`Mark ${row.name} as default`}
                                                    >
                                                        <Star className="size-4" />
                                                    </Button>
                                                )}
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            edit({
                                                                template:
                                                                    row.slug,
                                                            }).url
                                                        }
                                                        aria-label={`Edit ${row.name}`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        handleDelete(row)
                                                    }
                                                    aria-label={`Delete ${row.name}`}
                                                    disabled={
                                                        row.batch_count > 0
                                                    }
                                                    title={
                                                        row.batch_count > 0
                                                            ? 'In use by historical batches'
                                                            : undefined
                                                    }
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

ExportTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/export-templates' },
        { title: 'Export templates', href: '/admin/export-templates' },
    ],
};
