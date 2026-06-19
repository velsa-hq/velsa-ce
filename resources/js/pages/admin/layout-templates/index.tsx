import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Template = {
    id: number;
    name: string;
    category: string | null;
    description: string | null;
    space: { id: number; name: string } | null;
    object_count: number;
    seat_count: number;
    created_by: string | null;
    updated_at: string | null;
};

type Props = {
    templates: Template[];
};

const CATEGORY_LABEL: Record<string, string> = {
    banquet: 'Banquet',
    classroom: 'Classroom',
    theater: 'Theater',
    u_shape: 'U-shape',
    booth_grid: 'Booth grid',
    reception: 'Reception',
    other: 'Other',
};

export default function LayoutTemplatesIndex({ templates }: Props) {
    const onDelete = (template: Template) => {
        if (!confirm(`Delete template "${template.name}"?`)) {
            return;
        }

        router.delete(`/admin/layout-templates/${template.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Layout templates · Admin" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Layout templates
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Reusable floor-plan layouts surfaced in the diagram
                        editor. Global templates (no specific space) appear on
                        every booking; space-scoped templates only appear when
                        editing that space's plan. Create new templates from the
                        diagram editor's "Save as template" button.
                    </p>
                </header>

                {templates.length === 0 ? (
                    <div className="rounded-lg border border-sidebar-border/70 p-6 text-sm text-muted-foreground dark:border-sidebar-border">
                        No templates yet. Open a diagram, build a layout, and
                        click <strong>Save as template</strong> to seed this
                        library.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr className="text-left text-xs tracking-wider text-muted-foreground uppercase">
                                    <th className="px-3 py-2 font-medium">
                                        Name
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Category
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Scope
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Objects
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Seats
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Created by
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Updated
                                    </th>
                                    <th className="px-3 py-2 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {templates.map((t) => (
                                    <tr
                                        key={t.id}
                                        className="border-t border-sidebar-border/40 align-top dark:border-sidebar-border/60"
                                    >
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {t.name}
                                            </div>
                                            {t.description && (
                                                <div className="text-xs text-muted-foreground">
                                                    {t.description}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {t.category
                                                ? (CATEGORY_LABEL[t.category] ??
                                                  t.category)
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {t.space ? (
                                                t.space.name
                                            ) : (
                                                <span className="rounded-sm bg-muted px-1.5 py-0.5 tracking-wider text-muted-foreground uppercase">
                                                    global
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {t.object_count}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {t.seat_count}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {t.created_by ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {t.updated_at?.slice(0, 10) ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => onDelete(t)}
                                                data-tour-id="admin-lt-delete"
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
            </div>
        </>
    );
}

LayoutTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Layout templates', href: '/admin/layout-templates' },
    ],
};
