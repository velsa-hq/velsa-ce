import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';

type Template = {
    id: number;
    name: string;
    kind: string | null;
    kind_label: string | null;
    version: number;
    is_active: boolean;
    venue: { id: number; name: string } | null;
    venue_id: number | null;
    contracts_count: number;
    updated_at: string | null;
};

type Props = {
    templates: Template[];
    kinds: Array<{ value: string; label: string }>;
};

export default function DocumentTemplatesIndex({ templates, kinds }: Props) {
    const grouped = useMemo(() => {
        const byKind = new Map<string, Template[]>();

        for (const k of kinds) {
            byKind.set(k.value, []);
        }

        for (const t of templates) {
            if (!t.kind) {
                continue;
            }

            const list = byKind.get(t.kind) ?? [];
            list.push(t);
            byKind.set(t.kind, list);
        }

        return Array.from(byKind.entries()).map(([kind, rows]) => ({
            kind,
            label: kinds.find((k) => k.value === kind)?.label ?? kind,
            rows,
        }));
    }, [templates, kinds]);

    const onDelete = (t: Template) => {
        const used = t.contracts_count > 0;
        const extra = used
            ? `\n\nThis template has been used on ${t.contracts_count} contract(s). Historical contracts keep their rendered body; only the template is removed.`
            : '';

        if (!confirm(`Delete template "${t.name}"?${extra}`)) {
            return;
        }

        router.delete(`/admin/document-templates/${t.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Document templates · Admin" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Document templates
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Configure the proposal / contract / addendum /
                            invoice / payment-schedule templates that get
                            rendered when a sales rep clicks
                            <strong> Draft contract</strong> on a booking.
                            Templates are scoped to a venue or left global (used
                            everywhere); the booking-level draft flow picks the
                            most-specific active template per kind.
                        </p>
                    </div>
                    <Button asChild>
                        <Link
                            href="/admin/document-templates/create"
                            data-tour-id="admin-dt-new"
                        >
                            New template
                        </Link>
                    </Button>
                </header>

                <div className="flex flex-col gap-6">
                    {grouped.map(({ kind, label, rows }) => (
                        <section key={kind} className="flex flex-col gap-2">
                            <h2 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                {label}
                            </h2>
                            {rows.length === 0 ? (
                                <div className="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
                                    No {label.toLowerCase()} template yet.{' '}
                                    <Link
                                        href={`/admin/document-templates/create?kind=${kind}`}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        Create one
                                    </Link>
                                    .
                                </div>
                            ) : (
                                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/40 text-xs tracking-wider text-muted-foreground uppercase">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Name
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Scope
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Version
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Active
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Used on
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Updated
                                                </th>
                                                <th />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {rows.map((t) => (
                                                <tr
                                                    key={t.id}
                                                    className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                                >
                                                    <td className="px-3 py-2">
                                                        <Link
                                                            href={`/admin/document-templates/${t.id}`}
                                                            className="font-medium hover:underline"
                                                        >
                                                            {t.name}
                                                        </Link>
                                                    </td>
                                                    <td className="px-3 py-2 text-xs">
                                                        {t.venue ? (
                                                            t.venue.name
                                                        ) : (
                                                            <span className="rounded-sm bg-muted px-1.5 py-0.5 tracking-wider text-muted-foreground uppercase">
                                                                global
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs tabular-nums">
                                                        v{t.version}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs">
                                                        {t.is_active ? (
                                                            <span className="rounded-md bg-emerald-100 px-1.5 py-0.5 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200">
                                                                Active
                                                            </span>
                                                        ) : (
                                                            <span className="rounded-md bg-muted px-1.5 py-0.5 text-muted-foreground">
                                                                Inactive
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                                        {t.contracts_count}{' '}
                                                        contract(s)
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                                        {t.updated_at?.slice(
                                                            0,
                                                            10,
                                                        ) ?? '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-right">
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                onDelete(t)
                                                            }
                                                        >
                                                            Delete
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                    ))}
                </div>
            </div>
        </>
    );
}

DocumentTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Document templates', href: '/admin/document-templates' },
    ],
};
