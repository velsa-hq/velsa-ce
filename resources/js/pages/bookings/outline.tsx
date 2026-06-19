import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { OutlineItemModal } from '@/components/outline/outline-item-modal';
import type {
    OutlineDepartmentOption,
    OutlineStaffMember,
    OutlineTemplate,
} from '@/components/outline/outline-item-modal';
import { Button } from '@/components/ui/button';
import { wallClock } from '@/lib/datetime';

type Task = { id: number; label: string; is_done: boolean };

type Item = {
    id: number;
    scheduled_at: string | null;
    scheduled_at_edit: string;
    ends_at: string;
    duration_minutes: number;
    department: string;
    department_label: string;
    title: string;
    description: string | null;
    description_html: string;
    space_name: string | null;
    responsible_user_id: number | null;
    responsible_name: string | null;
    responsible_email: string | null;
    tasks: Task[];
};

type Props = {
    booking: {
        id: number;
        reference: string;
        name: string;
        start_at: string | null;
        end_at: string | null;
        venue_name: string | null;
    };
    outline: {
        id: number;
        published_version: number;
        published_at: string | null;
        is_published: boolean;
        notes: string | null;
    };
    items: Item[];
    departments: OutlineDepartmentOption[];
    item_templates: OutlineTemplate[];
    staff: OutlineStaffMember[];
};

const DEPT_COLORS: Record<string, string> = {
    setup: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    av: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-100',
    catering:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    security:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    cleaning:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    parking: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    reception:
        'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-900/40 dark:text-fuchsia-100',
    teardown:
        'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    ops_lead:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
};

function fmtTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export default function BookingOutline({
    booking,
    outline,
    items,
    departments,
    item_templates,
    staff,
}: Props) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [creating, setCreating] = useState(false);
    const defaultStart = booking.start_at ? booking.start_at.slice(0, 16) : '';

    // re-derive from live props so modal checklist toggles flow back in
    const editingItem = items.find((i) => i.id === editingId) ?? null;

    const closeModal = () => {
        setCreating(false);
        setEditingId(null);
    };

    const removeItem = (itemId: number) => {
        if (!window.confirm('Remove this item?')) {
            return;
        }

        router.delete(`/outline-items/${itemId}`, { preserveScroll: true });
    };

    const publish = () => {
        router.post(
            `/bookings/${booking.id}/outline/publish`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`${booking.reference} · Run of show`} />

            <OutlineItemModal
                open={creating || editingId !== null}
                mode={creating ? 'create' : 'edit'}
                onClose={closeModal}
                booking={{
                    id: booking.id,
                    name: booking.name,
                    reference: booking.reference,
                    venue_name: booking.venue_name,
                }}
                departments={departments}
                item={editingItem}
                spaceName={editingItem?.space_name}
                templates={item_templates}
                staff={staff}
                createUrl={`/bookings/${booking.id}/outline/items`}
                defaultScheduledAt={defaultStart}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Link href="/bookings" className="hover:underline">
                                Bookings
                            </Link>
                            <span>·</span>
                            <Link
                                href={`/bookings/${booking.id}`}
                                className="font-mono hover:underline"
                            >
                                {booking.reference}
                            </Link>
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {booking.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Run of show · {booking.venue_name ?? '-'} ·{' '}
                            {fmtDate(booking.start_at)}{' '}
                            {fmtTime(booking.start_at)} -{' '}
                            {fmtTime(booking.end_at)}
                        </p>
                        {outline.is_published ? (
                            <p className="text-xs text-emerald-700 dark:text-emerald-300">
                                Published v{outline.published_version} ·{' '}
                                {outline.published_at
                                    ? new Date(
                                          outline.published_at,
                                      ).toLocaleString()
                                    : ''}
                            </p>
                        ) : (
                            <p className="text-xs text-amber-700 dark:text-amber-300">
                                Draft · unpublished
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={`/bookings/${booking.id}/outline.pdf`}
                                target="_blank"
                                rel="noopener"
                                data-tour-id="outline-pdf"
                            >
                                Run sheet (PDF)
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setEditingId(null);
                                setCreating(true);
                            }}
                            data-tour-id="outline-add-item"
                        >
                            Add item
                        </Button>
                        <Button
                            onClick={publish}
                            data-tour-id="outline-publish"
                        >
                            Publish outline
                        </Button>
                    </div>
                </header>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    When
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Dur.
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Department
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Title
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Space
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Responsible
                                </th>
                                <th className="px-3 py-2 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No items in this outline yet.
                                    </td>
                                </tr>
                            ) : (
                                items.map((item, idx) => (
                                    <tr
                                        key={item.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                            <div>
                                                {fmtDate(item.scheduled_at)}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {fmtTime(item.scheduled_at)}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {item.duration_minutes}m
                                        </td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${DEPT_COLORS[item.department] ?? ''}`}
                                            >
                                                {item.department_label}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setEditingId(item.id)
                                                }
                                                className="text-left text-sm hover:underline"
                                                data-tour-id="outline-edit-inline"
                                            >
                                                {item.title}
                                            </button>
                                            {item.description_html ? (
                                                <div
                                                    className="prose prose-xs dark:prose-invert mt-0.5 max-w-none text-xs text-muted-foreground"
                                                    dangerouslySetInnerHTML={{
                                                        __html: item.description_html,
                                                    }}
                                                />
                                            ) : null}
                                            <RowChecklist item={item} />
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {item.space_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {item.responsible_name ??
                                                item.responsible_email ??
                                                'unassigned'}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setEditingId(item.id)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() =>
                                                    removeItem(item.id)
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

// inline tickable checklist for day-of progress; full management lives in the modal
function RowChecklist({ item }: { item: Item }) {
    if (item.tasks.length === 0) {
        return null;
    }

    const done = item.tasks.filter((t) => t.is_done).length;

    const toggle = (taskId: number) =>
        router.patch(
            `/outline-item-tasks/${taskId}/toggle`,
            {},
            { preserveScroll: true, preserveState: true },
        );

    return (
        <div className="mt-1.5 flex flex-col gap-1">
            <div className="text-[10px] font-medium text-muted-foreground">
                Checklist {done}/{item.tasks.length}
            </div>
            {item.tasks.map((t) => (
                <label key={t.id} className="flex items-center gap-1.5 text-xs">
                    <input
                        type="checkbox"
                        checked={t.is_done}
                        onChange={() => toggle(t.id)}
                        className="size-3.5 rounded border-border accent-primary"
                    />
                    <span
                        className={
                            t.is_done
                                ? 'text-muted-foreground line-through'
                                : ''
                        }
                    >
                        {t.label}
                    </span>
                </label>
            ))}
        </div>
    );
}

BookingOutline.layout = {
    breadcrumbs: [
        { title: 'Bookings', href: '/bookings' },
        { title: 'Run of show', href: '#' },
    ],
};
