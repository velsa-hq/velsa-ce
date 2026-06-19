import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    SortableContext,
    arrayMove,
    rectSortingStrategy,
    sortableKeyboardCoordinates,
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, GripVertical, LayoutGrid } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType } from 'react';
import { BookingsByStatus } from '@/components/dashboard/bookings-by-status';
import { KpiStrip } from '@/components/dashboard/kpi-strip';
import { MyOpenLeads } from '@/components/dashboard/my-open-leads';
import { MyUpcomingBookings } from '@/components/dashboard/my-upcoming-bookings';
import { NeedsAttention } from '@/components/dashboard/needs-attention';
import { PastDueInvoices } from '@/components/dashboard/past-due-invoices';
import { PipelineByStage } from '@/components/dashboard/pipeline-by-stage';
import { QuickLinks } from '@/components/dashboard/quick-links';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { RevenueTrend } from '@/components/dashboard/revenue-trend';
import { TodayOutline } from '@/components/dashboard/today-outline';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';

type Tile = {
    key: string;
    label: string;
    component: string;
    column_span: number;
    data: Record<string, unknown>;
};

type CatalogEntry = {
    key: string;
    label: string;
    description: string;
    column_span: number;
};

type Props = {
    tiles: Tile[];
    catalog: CatalogEntry[];
    selected_keys: string[];
    last_sign_in_at: string | null;
};

const COMPONENTS: Record<string, ComponentType<any>> = {
    kpi_strip: KpiStrip,
    needs_attention: NeedsAttention,
    revenue_trend: RevenueTrend,
    pipeline_by_stage: PipelineByStage,
    bookings_by_status: BookingsByStatus,
    today_outline: TodayOutline,
    recent_activity: RecentActivity,
    my_open_leads: MyOpenLeads,
    my_upcoming_bookings: MyUpcomingBookings,
    past_due_invoices: PastDueInvoices,
    quick_links: QuickLinks,
};

function colSpanClass(span: number): string {
    switch (span) {
        case 12:
            return 'col-span-12';
        case 8:
            return 'col-span-12 lg:col-span-8';
        case 6:
            return 'col-span-12 lg:col-span-6';
        case 4:
            return 'col-span-12 md:col-span-6 lg:col-span-4';
        case 3:
            return 'col-span-12 md:col-span-6 lg:col-span-3';
        default:
            return 'col-span-12';
    }
}

/** sortable tile; only the grip handle starts a drag so tile links stay clickable */
function SortableTile({ tile }: { tile: Tile }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: tile.key });
    const Component = COMPONENTS[tile.component];

    if (!Component) {
        return null;
    }

    const style = {
        transform: CSS.Translate.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={`group/tile relative ${colSpanClass(tile.column_span)} ${
                isDragging ? 'z-10 opacity-70' : ''
            }`}
        >
            <button
                type="button"
                {...attributes}
                {...listeners}
                aria-label={`Drag to reorder ${tile.label}`}
                title="Drag to reorder"
                data-tour-id="dashboard-tile-handle"
                className="absolute top-2 right-2 z-10 grid size-6 cursor-grab touch-none place-items-center rounded-md text-muted-foreground/50 opacity-0 transition group-hover/tile:opacity-100 hover:bg-muted hover:text-foreground focus-visible:opacity-100 active:cursor-grabbing"
            >
                <GripVertical className="size-4" />
            </button>
            <Component data={tile.data} />
        </div>
    );
}

export default function Dashboard({
    tiles,
    catalog,
    selected_keys,
    last_sign_in_at,
}: Props) {
    const [pickerOpen, setPickerOpen] = useState(false);

    const lastSignIn = last_sign_in_at
        ? new Date(last_sign_in_at).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : null;

    // local order for snappy drag/drop; re-sync during render when the server set changes
    const [ordered, setOrdered] = useState<Tile[]>(tiles);
    const [seenTiles, setSeenTiles] = useState(tiles);

    if (tiles !== seenTiles) {
        setSeenTiles(tiles);
        setOrdered(tiles);
    }

    const sensors = useSensors(
        // small drag threshold so clicks on tile content aren't hijacked
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const persistOrder = (keys: string[]) => {
        router.put(
            '/dashboard/preferences',
            { tiles: keys },
            { preserveScroll: true, preserveState: true },
        );
    };

    const onDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setOrdered((prev) => {
            const from = prev.findIndex((t) => t.key === active.id);
            const to = prev.findIndex((t) => t.key === over.id);

            if (from === -1 || to === -1) {
                return prev;
            }

            const next = arrayMove(prev, from, to);
            persistOrder(next.map((t) => t.key));

            return next;
        });
    };

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Your customizable operational overview - drag a tile
                            by its handle to rearrange.
                        </p>
                        {lastSignIn && (
                            <p
                                className="text-xs text-muted-foreground"
                                data-tour-id="dashboard-last-sign-in"
                            >
                                Last sign-in: {lastSignIn}
                            </p>
                        )}
                    </div>
                    <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                data-tour-id="dashboard-customize"
                            >
                                <LayoutGrid className="size-4" />
                                Customize
                            </Button>
                        </DialogTrigger>
                        <TilePicker
                            catalog={catalog}
                            selected={selected_keys}
                            onDone={() => setPickerOpen(false)}
                        />
                    </Dialog>
                </header>

                {ordered.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-sidebar-border/70 p-12 text-center dark:border-sidebar-border">
                        <p className="text-sm text-muted-foreground">
                            You haven't selected any tiles. Click{' '}
                            <strong>Customize</strong> to pick some.
                        </p>
                    </div>
                ) : (
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={onDragEnd}
                    >
                        <SortableContext
                            items={ordered.map((t) => t.key)}
                            strategy={rectSortingStrategy}
                        >
                            <div className="grid grid-cols-12 gap-3">
                                {ordered.map((tile) => (
                                    <SortableTile key={tile.key} tile={tile} />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                )}
            </div>
        </>
    );
}

function TilePicker({
    catalog,
    selected,
    onDone,
}: {
    catalog: CatalogEntry[];
    selected: string[];
    onDone: () => void;
}) {
    // selected first (in order), then the rest
    const initial = useMemo(() => {
        const selectedSet = new Set(selected);
        const byKey = new Map(catalog.map((c) => [c.key, c]));
        const ordered: { entry: CatalogEntry; on: boolean }[] = [];
        selected.forEach((key) => {
            const entry = byKey.get(key);

            if (entry) {
                ordered.push({ entry, on: true });
            }
        });
        catalog.forEach((entry) => {
            if (!selectedSet.has(entry.key)) {
                ordered.push({ entry, on: false });
            }
        });

        return ordered;
    }, [catalog, selected]);

    const [items, setItems] = useState(initial);
    const [saving, setSaving] = useState(false);

    const toggle = (key: string) => {
        setItems((prev) =>
            prev.map((it) =>
                it.entry.key === key ? { ...it, on: !it.on } : it,
            ),
        );
    };

    const move = (index: number, delta: number) => {
        const target = index + delta;

        if (target < 0 || target >= items.length) {
            return;
        }

        setItems((prev) => {
            const next = [...prev];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    };

    const save = () => {
        const tileKeys = items.filter((it) => it.on).map((it) => it.entry.key);
        setSaving(true);
        router.put(
            '/dashboard/preferences',
            { tiles: tileKeys },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSaving(false);
                    onDone();
                },
            },
        );
    };

    return (
        <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Customize your dashboard</DialogTitle>
                <DialogDescription>
                    Toggle which tiles you want to see and use the arrows to
                    reorder them. You can also drag tiles directly on the
                    dashboard by their handle.
                </DialogDescription>
            </DialogHeader>

            <ul className="flex flex-col gap-1.5">
                {items.map((item, idx) => (
                    <li
                        key={item.entry.key}
                        className={`flex items-start gap-3 rounded-md border p-3 transition-colors ${
                            item.on
                                ? 'border-sidebar-border bg-card'
                                : 'border-dashed border-sidebar-border/50 bg-muted/30'
                        }`}
                    >
                        <Checkbox
                            id={`tile-${item.entry.key}`}
                            checked={item.on}
                            onCheckedChange={() => toggle(item.entry.key)}
                            className="mt-0.5"
                        />
                        <label
                            htmlFor={`tile-${item.entry.key}`}
                            className="flex flex-1 cursor-pointer flex-col gap-0.5"
                        >
                            <span className="text-sm leading-none font-medium">
                                {item.entry.label}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {item.entry.description}
                            </span>
                        </label>
                        <div className="flex flex-col gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => move(idx, -1)}
                                disabled={idx === 0}
                                className="size-7 p-0"
                                aria-label={`Move ${item.entry.label} up`}
                            >
                                <ArrowUp className="size-3.5" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => move(idx, 1)}
                                disabled={idx === items.length - 1}
                                className="size-7 p-0"
                                aria-label={`Move ${item.entry.label} down`}
                            >
                                <ArrowDown className="size-3.5" />
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onDone}
                    disabled={saving}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    onClick={save}
                    disabled={saving}
                    data-tour-id="tile-picker-save"
                >
                    {saving ? 'Saving...' : 'Save'}
                </Button>
            </DialogFooter>
        </DialogContent>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
