import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    MouseSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { create } from '@/routes/leads';
import { archive } from '@/routes/pipeline';

type LeadCard = {
    id: number;
    name: string;
    client_name: string | null;
    venue_name: string | null;
    venue_slug: string | null;
    owner_email: string | null;
    estimated_cents: number;
    probability: number;
    weighted_cents: number;
    expected_close_date: string | null;
    is_overdue: boolean;
    lost_reason: string | null;
    converted_booking: { id: number; reference: string } | null;
};

type Column = {
    key: string;
    label: string;
    is_terminal: boolean;
    is_lost: boolean;
    is_won: boolean;
    totals: { count: number; estimated_cents: number; weighted_cents: number };
    leads: LeadCard[];
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    columns: Column[];
    venues: VenueOption[];
    filters: { venue_id: number | null };
    summary: { open_count: number; open_weighted_cents: number };
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function columnTone(col: Column, isOver: boolean): string {
    const base = col.is_won
        ? 'border-emerald-300 bg-emerald-50/50 dark:border-emerald-700 dark:bg-emerald-950/30'
        : col.is_lost
          ? 'border-rose-300 bg-rose-50/50 dark:border-rose-700 dark:bg-rose-950/30'
          : 'border-sidebar-border/70 bg-muted/30 dark:border-sidebar-border';
    const dropRing = isOver
        ? col.is_won
            ? 'ring-2 ring-emerald-400'
            : col.is_lost
              ? 'ring-2 ring-rose-400'
              : 'ring-2 ring-primary/60'
        : '';

    return `${base} ${dropRing}`;
}

export default function PipelineIndex({
    columns: initial,
    venues,
    filters,
    summary,
}: Props) {
    const [columns, setColumns] = useState(initial);
    const [activeLead, setActiveLead] = useState<LeadCard | null>(null);
    const [pendingLost, setPendingLost] = useState<{
        lead: LeadCard;
        fromKey: string;
    } | null>(null);
    const [lostReason, setLostReason] = useState('');

    const sensors = useSensors(
        // 5px drag threshold avoids hijacking simple clicks on cards.
        useSensor(MouseSensor, { activationConstraint: { distance: 5 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 150, tolerance: 5 },
        }),
        useSensor(KeyboardSensor),
    );

    const onVenueChange = (id: number | null) => {
        router.get('/pipeline', id ? { venue_id: id } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const findCard = (
        leadId: number,
    ): { lead: LeadCard; columnKey: string } | null => {
        for (const col of columns) {
            const lead = col.leads.find((l) => l.id === leadId);

            if (lead) {
                return { lead, columnKey: col.key };
            }
        }

        return null;
    };

    const handleDragStart = (e: DragStartEvent) => {
        const id = Number(String(e.active.id).replace('lead-', ''));
        const found = findCard(id);

        if (found) {
            setActiveLead(found.lead);
        }
    };

    const moveCardLocally = (
        leadId: number,
        fromKey: string,
        toKey: string,
        patch?: Partial<LeadCard>,
    ) => {
        setColumns((prev) => {
            const next = prev.map((c) => ({ ...c, leads: [...c.leads] }));
            const fromCol = next.find((c) => c.key === fromKey);
            const toCol = next.find((c) => c.key === toKey);

            if (!fromCol || !toCol) {
                return prev;
            }

            const idx = fromCol.leads.findIndex((l) => l.id === leadId);

            if (idx === -1) {
                return prev;
            }

            const [card] = fromCol.leads.splice(idx, 1);
            toCol.leads.unshift({ ...card, ...patch });

            return next;
        });
    };

    const submitStage = (
        leadId: number,
        stage: string,
        fromKey: string,
        toKey: string,
        lostReasonValue?: string,
    ) => {
        router.patch(
            `/leads/${leadId}/stage`,
            { stage, lost_reason: lostReasonValue ?? null },
            {
                preserveScroll: true,
                onError: () => {
                    // Revert optimistic move on failure.
                    moveCardLocally(leadId, toKey, fromKey);
                },
            },
        );
    };

    const handleDragEnd = (e: DragEndEvent) => {
        setActiveLead(null);

        if (!e.over) {
            return;
        }

        const leadId = Number(String(e.active.id).replace('lead-', ''));
        const toKey = String(e.over.id).replace('col-', '');
        const found = findCard(leadId);

        if (!found || found.columnKey === toKey) {
            return;
        }

        const target = columns.find((c) => c.key === toKey);

        if (!target) {
            return;
        }

        // Lost requires a reason; pop the dialog before committing.
        if (target.is_lost) {
            setPendingLost({ lead: found.lead, fromKey: found.columnKey });
            setLostReason('');

            return;
        }

        moveCardLocally(leadId, found.columnKey, toKey, { lost_reason: null });
        submitStage(leadId, toKey, found.columnKey, toKey);
    };

    const confirmLost = () => {
        if (!pendingLost) {
            return;
        }

        const reason = lostReason.trim();

        if (reason === '') {
            return;
        }

        const { lead, fromKey } = pendingLost;
        moveCardLocally(lead.id, fromKey, 'lost', { lost_reason: reason });
        submitStage(lead.id, 'lost', fromKey, 'lost', reason);
        setPendingLost(null);
        setLostReason('');
    };

    return (
        <>
            <Head title="Sales pipeline" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Sales pipeline
                            <HelpLink slug="pipeline" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {summary.open_count} open ·{' '}
                            {money(summary.open_weighted_cents)} weighted · drag
                            any card to move it through the funnel
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            Venue
                            <select
                                value={filters.venue_id ?? ''}
                                onChange={(e) =>
                                    onVenueChange(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    )
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 dark:border-sidebar-border"
                            >
                                <option value="">All venues</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <Link
                            href={archive().url}
                            className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Archive
                        </Link>
                        <Button asChild size="sm">
                            <Link
                                href={create().url}
                                data-tour-id="pipeline-new-opportunity"
                            >
                                + New opportunity
                            </Link>
                        </Button>
                    </div>
                </header>

                <DndContext
                    sensors={sensors}
                    onDragStart={handleDragStart}
                    onDragEnd={handleDragEnd}
                    onDragCancel={() => setActiveLead(null)}
                >
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        {columns.map((col) => (
                            <Column key={col.key} col={col} />
                        ))}
                    </div>
                    <DragOverlay>
                        {activeLead && (
                            <LeadCardView
                                lead={activeLead}
                                col={
                                    columns.find((c) =>
                                        c.leads.some(
                                            (l) => l.id === activeLead.id,
                                        ),
                                    ) ?? columns[0]
                                }
                                isOverlay
                            />
                        )}
                    </DragOverlay>
                </DndContext>
            </div>

            <Dialog
                open={pendingLost !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingLost(null);
                        setLostReason('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark as Lost</DialogTitle>
                        <DialogDescription>
                            Briefly capture why this lead is being marked Lost.
                            The reason shows on the card and in the audit log.
                        </DialogDescription>
                    </DialogHeader>
                    <label className="flex flex-col gap-1 text-xs font-medium">
                        Reason
                        <Input
                            autoFocus
                            value={lostReason}
                            onChange={(e) => setLostReason(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    confirmLost();
                                }
                            }}
                            placeholder="e.g. went with competitor"
                        />
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setPendingLost(null);
                                setLostReason('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={confirmLost}
                            disabled={lostReason.trim() === ''}
                        >
                            Mark Lost
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Column({ col }: { col: Column }) {
    const { setNodeRef, isOver } = useDroppable({ id: `col-${col.key}` });

    return (
        <section
            ref={setNodeRef}
            className={`flex flex-col gap-3 rounded-xl border p-3 transition-shadow ${columnTone(col, isOver)}`}
        >
            <header className="flex flex-col gap-0.5">
                <div className="flex items-baseline justify-between">
                    <h2 className="text-sm font-semibold">{col.label}</h2>
                    <span className="text-xs text-muted-foreground">
                        {col.totals.count}
                    </span>
                </div>
                <div className="text-xs text-muted-foreground">
                    {col.is_lost
                        ? money(col.totals.estimated_cents)
                        : `${money(col.totals.weighted_cents)} weighted`}
                </div>
            </header>

            <div className="flex min-h-[4rem] flex-col gap-2">
                {col.leads.length === 0 ? (
                    <p className="rounded-md border border-dashed border-sidebar-border/70 p-3 text-center text-xs text-muted-foreground italic dark:border-sidebar-border">
                        no leads
                    </p>
                ) : (
                    col.leads.map((lead) => (
                        <DraggableCard key={lead.id} lead={lead} col={col} />
                    ))
                )}
            </div>
        </section>
    );
}

function DraggableCard({ lead, col }: { lead: LeadCard; col: Column }) {
    const { setNodeRef, listeners, attributes, isDragging } = useDraggable({
        id: `lead-${lead.id}`,
    });

    return (
        <div
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            className={`touch-none ${isDragging ? 'opacity-30' : ''}`}
        >
            <LeadCardView lead={lead} col={col} />
        </div>
    );
}

function LeadCardView({
    lead,
    col,
    isOverlay = false,
}: {
    lead: LeadCard;
    col: Column;
    isOverlay?: boolean;
}) {
    // Overdue is decided server-side (it honors the configurable grace
    // window); the card just renders the flag.
    const overdue = lead.is_overdue;

    return (
        <div
            className={`flex flex-col gap-1 rounded-lg border bg-background p-3 text-sm ${
                overdue
                    ? 'border-amber-400 dark:border-amber-600'
                    : 'border-sidebar-border/70 dark:border-sidebar-border'
            } ${isOverlay ? 'cursor-grabbing shadow-lg' : 'cursor-grab'}`}
        >
            <Link
                href={`/leads/${lead.id}`}
                onPointerDownCapture={(e) => e.stopPropagation()}
                className="-m-3 flex flex-col gap-1 rounded-lg p-3 transition-colors hover:bg-muted"
            >
                <div className="flex items-start justify-between gap-2">
                    <span className="min-w-0 leading-tight font-medium break-words">
                        {lead.name}
                    </span>
                    <span className="shrink-0 font-mono text-xs text-muted-foreground">
                        {money(lead.estimated_cents)}
                    </span>
                </div>
                <div className="truncate text-xs text-muted-foreground">
                    {lead.client_name}
                </div>
                <div className="flex items-center justify-between gap-2 text-[11px] text-muted-foreground">
                    <span className="min-w-0 truncate">
                        {lead.venue_name ?? '-'}
                    </span>
                    <span className="shrink-0">
                        {col.is_terminal
                            ? (lead.lost_reason ?? '✓')
                            : `${Math.round(lead.probability * 100)}%`}
                    </span>
                </div>
                {lead.expected_close_date && !col.is_terminal ? (
                    <div
                        className={`flex items-center gap-1 text-[11px] ${
                            overdue
                                ? 'font-medium text-amber-700 dark:text-amber-500'
                                : 'text-muted-foreground'
                        }`}
                    >
                        {overdue ? (
                            <span
                                data-tour-id="pipeline-overdue-badge"
                                className="inline-flex items-center rounded-sm bg-amber-100 px-1 py-px text-[10px] font-semibold tracking-wide text-amber-800 uppercase dark:bg-amber-950 dark:text-amber-300"
                            >
                                Overdue
                            </span>
                        ) : null}
                        <span>
                            closes{' '}
                            {new Date(
                                lead.expected_close_date + 'T00:00:00',
                            ).toLocaleDateString()}
                        </span>
                    </div>
                ) : null}
            </Link>
            {col.is_won ? (
                lead.converted_booking ? (
                    <Link
                        href={`/bookings/${lead.converted_booking.id}`}
                        onPointerDownCapture={(e) => e.stopPropagation()}
                        className="mt-1 inline-flex items-center justify-center rounded-md border border-emerald-300 bg-white px-2 py-1 text-[11px] font-medium text-emerald-800 hover:bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200 dark:hover:bg-emerald-950"
                    >
                        ✓ {lead.converted_booking.reference}
                    </Link>
                ) : (
                    <Link
                        href={`/bookings/create?from_lead=${lead.id}`}
                        onPointerDownCapture={(e) => e.stopPropagation()}
                        className="mt-1 inline-flex items-center justify-center rounded-md bg-primary px-2 py-1 text-[11px] font-medium text-primary-foreground hover:opacity-90"
                    >
                        + Convert to booking
                    </Link>
                )
            ) : null}
        </div>
    );
}

PipelineIndex.layout = {
    breadcrumbs: [{ title: 'Pipeline', href: '/pipeline' }],
};
