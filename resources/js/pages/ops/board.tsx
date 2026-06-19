import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import HelpLink from '@/components/help-link';
import { OpsViewSwitcher } from '@/components/ops/ops-view-switcher';
import { OutlineItemModal } from '@/components/outline/outline-item-modal';
import { deptCell, deptSwatch } from '@/lib/department-colors';

type Task = { id: number; label: string; is_done: boolean };

type Item = {
    id: number;
    scheduled_at: string;
    scheduled_at_edit: string;
    duration_minutes: number;
    department: string;
    title: string;
    description: string | null;
    description_html: string;
    task_total: number;
    task_done: number;
    tasks: Task[];
    space_name: string | null;
    responsible_email: string | null;
    booking: {
        id: number;
        reference: string;
        name: string;
        venue_name: string | null;
    };
};

type Department = { value: string; label: string; color: string };
type DepartmentOption = { value: string; label: string };
type VenueOption = { id: number; name: string; slug: string };

type Props = {
    grid: Record<string, Record<string, Item[]>>;
    date_keys: string[];
    departments: Department[];
    filters: {
        days: number;
        venue_id: number | null;
        department: string | null;
    };
    venues: VenueOption[];
    department_options: DepartmentOption[];
    total_items: number;
};

function fmtDayHeader(iso: string): {
    day: string;
    date: string;
    isToday: boolean;
} {
    const d = new Date(iso + 'T12:00:00');
    const today = new Date();
    today.setHours(12, 0, 0, 0);

    return {
        day: d.toLocaleDateString(undefined, { weekday: 'short' }),
        date: d.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        }),
        isToday: d.toDateString() === today.toDateString(),
    };
}

export default function OpsBoard({
    grid,
    date_keys,
    departments,
    filters,
    venues,
    department_options,
    total_items,
}: Props) {
    const updateFilter = (
        key: 'days' | 'venue_id' | 'department',
        value: string,
    ) => {
        const params: Record<string, string> = {};
        const updated = { ...filters, [key]: value || null };

        if (updated.days && updated.days !== 14) {
            params.days = String(updated.days);
        }

        if (updated.venue_id) {
            params.venue_id = String(updated.venue_id);
        }

        if (updated.department) {
            params.department = String(updated.department);
        }

        router.get('/ops/board', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const [editingId, setEditingId] = useState<number | null>(null);

    // Flatten the grid to a by-id lookup so the modal always reflects the
    // live item after a checklist toggle re-fetches the board.
    const itemsById = useMemo(() => {
        const map: Record<number, Item> = {};

        for (const byDept of Object.values(grid)) {
            for (const cell of Object.values(byDept)) {
                for (const item of cell) {
                    map[item.id] = item;
                }
            }
        }

        return map;
    }, [grid]);

    const editingItem =
        editingId !== null ? (itemsById[editingId] ?? null) : null;

    return (
        <>
            <Head title="Ops board" />

            {editingItem ? (
                <OutlineItemModal
                    open
                    mode="edit"
                    onClose={() => setEditingId(null)}
                    booking={editingItem.booking}
                    departments={department_options}
                    item={editingItem}
                    spaceName={editingItem.space_name}
                />
            ) : null}

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-2">
                        <OpsViewSwitcher current="board" />
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Ops board
                            <HelpLink slug="operations/ops-board" />
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Next {filters.days} days · {total_items} outline
                            items across {departments.length}{' '}
                            {departments.length === 1
                                ? 'department'
                                : 'departments'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <select
                            value={filters.days}
                            onChange={(e) =>
                                updateFilter('days', e.target.value)
                            }
                            className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                        >
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="21">21 days</option>
                            <option value="28">28 days</option>
                        </select>
                        <select
                            value={filters.venue_id ?? ''}
                            onChange={(e) =>
                                updateFilter('venue_id', e.target.value)
                            }
                            className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                        >
                            <option value="">All venues</option>
                            {venues.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.department ?? ''}
                            onChange={(e) =>
                                updateFilter('department', e.target.value)
                            }
                            className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                        >
                            <option value="">All departments</option>
                            {department_options.map((d) => (
                                <option key={d.value} value={d.value}>
                                    {d.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </header>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="sticky left-0 z-10 bg-muted/50 px-3 py-2 text-left font-medium">
                                    Department
                                </th>
                                {date_keys.map((d) => {
                                    const { day, date, isToday } =
                                        fmtDayHeader(d);

                                    return (
                                        <th
                                            key={d}
                                            className={`min-w-[160px] px-2 py-2 text-left font-medium ${isToday ? 'bg-amber-100 dark:bg-amber-900/40' : ''}`}
                                        >
                                            <div className="text-xs font-semibold">
                                                {day}
                                            </div>
                                            <div className="text-[10px] text-muted-foreground">
                                                {date}
                                            </div>
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody>
                            {departments.map((dept) => (
                                <tr
                                    key={dept.value}
                                    className="border-t border-sidebar-border/40 align-top dark:border-sidebar-border/60"
                                >
                                    <td
                                        className={`sticky left-0 z-10 px-3 py-2 text-xs font-semibold whitespace-nowrap ${deptCell(dept.color)}`}
                                    >
                                        <span className="inline-flex items-center gap-2">
                                            <span
                                                className={`inline-block size-2 rounded-full ${deptSwatch(dept.color)}`}
                                                aria-hidden
                                            />
                                            {dept.label}
                                        </span>
                                    </td>
                                    {date_keys.map((date) => {
                                        const items =
                                            grid[date]?.[dept.value] ?? [];

                                        return (
                                            <td
                                                key={date + dept.value}
                                                className="border-l border-sidebar-border/40 px-1 py-1 dark:border-sidebar-border/60"
                                            >
                                                {items.length === 0 ? (
                                                    <div className="h-8" />
                                                ) : (
                                                    <div className="flex flex-col gap-1">
                                                        {items.map((item) => (
                                                            <div
                                                                key={item.id}
                                                                className={`flex flex-col gap-0.5 rounded border p-1 text-[10px] ${deptCell(dept.color)}`}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setEditingId(
                                                                            item.id,
                                                                        )
                                                                    }
                                                                    className="flex flex-col gap-0.5 text-left hover:underline"
                                                                >
                                                                    <span className="font-mono font-medium">
                                                                        {
                                                                            item.scheduled_at
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            item.duration_minutes
                                                                        }
                                                                        m
                                                                    </span>
                                                                    <span className="line-clamp-2 leading-tight">
                                                                        {
                                                                            item.title
                                                                        }
                                                                    </span>
                                                                </button>
                                                                <Link
                                                                    href={`/bookings/${item.booking.id}`}
                                                                    className="line-clamp-1 text-muted-foreground hover:text-foreground hover:underline"
                                                                >
                                                                    {
                                                                        item
                                                                            .booking
                                                                            .name
                                                                    }
                                                                </Link>
                                                                {item.task_total >
                                                                0 ? (
                                                                    <span className="text-muted-foreground">
                                                                        ☑{' '}
                                                                        {
                                                                            item.task_done
                                                                        }
                                                                        /
                                                                        {
                                                                            item.task_total
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

OpsBoard.layout = {
    breadcrumbs: [{ title: 'Ops board', href: '/ops/board' }],
};
