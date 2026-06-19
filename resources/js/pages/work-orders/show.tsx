import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { wallClock } from '@/lib/datetime';
import { show as bookingShow } from '@/routes/bookings';
import { show as venueShow } from '@/routes/venues';
import { index } from '@/routes/work-orders';

type VenueOption = { id: number; name: string; slug: string };
type KindOption = { value: string; label: string };
type Assignee = { id: number; name: string; email: string };

const PRIORITIES: { value: number; label: string }[] = [
    { value: 1, label: 'P1 · Critical' },
    { value: 2, label: 'P2 · High' },
    { value: 3, label: 'P3 · Normal' },
    { value: 4, label: 'P4 · Low' },
    { value: 5, label: 'P5 · Backlog' },
];

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

type Item = {
    id: number;
    resource_inventory_id: number | null;
    name: string;
    sku: string | null;
    quantity: number | null;
    unit: string | null;
    unit_cost_cents: number | null;
    action: string | null;
    notes: string | null;
    applied_at: string | null;
};

type ItemAction = { value: string; label: string };
type ResourceOption = {
    id: number;
    name: string;
    sku: string | null;
    quantity_available: number;
};

type WorkOrder = {
    id: number;
    reference: string;
    title: string;
    description: string | null;
    kind: string | null;
    status: string;
    priority: number;
    scheduled_for: string | null;
    completed_at: string | null;
    cost_cents: number | null;
    is_overdue: boolean;
    venue: { id: number; slug: string; name: string } | null;
    assigned_to_user_id: number | null;
    assignee: { name: string; email: string } | null;
    requester: { name: string; email: string } | null;
    template: { name: string } | null;
    department: string | null;
    booking: { id: number; reference: string; name: string } | null;
    exhibitor_source: {
        exhibitor_id: number;
        company_name: string;
        booth_assignment: string | null;
        order_id: number;
        order_number: string;
    } | null;
    items: Item[];
};

type Props = {
    work_order: WorkOrder;
    venues: VenueOption[];
    kinds: KindOption[];
    assignees: Assignee[];
    item_actions: ItemAction[];
    resources: ResourceOption[];
};

const STATUS_COLORS: Record<string, string> = {
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    open: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    assigned: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    in_progress:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    completed:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

const PRIORITY_LABEL: Record<number, string> = {
    1: 'Critical',
    2: 'High',
    3: 'Normal',
    4: 'Low',
    5: 'Backlog',
};

function titleCase(value: string | null): string {
    return value
        ? value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
        : '-';
}

function money(cents: number | null): string {
    if (cents == null) {
        return '-';
    }

    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function fmtDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function WorkOrderShow({
    work_order: wo,
    venues,
    kinds,
    assignees,
    item_actions,
    resources,
}: Props) {
    const [editing, setEditing] = useState(false);
    const [itemEditing, setItemEditing] = useState<Item | null>(null);
    const [itemCreating, setItemCreating] = useState(false);
    const isOpen = wo.status !== 'completed' && wo.status !== 'cancelled';

    const itemsSubtotal = wo.items.reduce(
        (sum, i) => sum + (i.unit_cost_cents ?? 0) * (i.quantity ?? 1),
        0,
    );

    const removeItem = (item: Item) => {
        if (!window.confirm(`Remove "${item.name}" from this work order?`)) {
            return;
        }

        router.delete(`/work-order-items/${item.id}`, { preserveScroll: true });
    };

    const setStatus = (status: string) =>
        router.patch(
            `/work-orders/${wo.id}/status`,
            { status },
            { preserveScroll: true },
        );

    const remove = () => {
        if (!window.confirm(`Delete ${wo.reference}? This can't be undone.`)) {
            return;
        }

        router.delete(`/work-orders/${wo.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${wo.reference} · Work order`} />

            <WorkOrderEditModal
                wo={wo}
                venues={venues}
                kinds={kinds}
                assignees={assignees}
                open={editing}
                onClose={() => setEditing(false)}
            />

            <WorkOrderItemModal
                workOrderId={wo.id}
                item={itemEditing}
                actions={item_actions}
                resources={resources}
                open={itemCreating || itemEditing !== null}
                onClose={() => {
                    setItemCreating(false);
                    setItemEditing(null);
                }}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Link
                    href={index().url}
                    className="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    Work orders
                </Link>

                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {wo.title}
                            </h1>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[wo.status] ?? ''}`}
                            >
                                {titleCase(wo.status)}
                            </span>
                            {wo.is_overdue ? (
                                <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 dark:bg-rose-900/40 dark:text-rose-100">
                                    Overdue
                                </span>
                            ) : null}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            <span className="font-mono">{wo.reference}</span>
                            {wo.venue ? (
                                <>
                                    {' · '}
                                    <Link
                                        href={venueShow(wo.venue.slug).url}
                                        className="hover:text-foreground hover:underline"
                                    >
                                        {wo.venue.name}
                                    </Link>
                                </>
                            ) : null}
                            {' · '}
                            {titleCase(wo.kind)}
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {isOpen ? (
                            <>
                                {wo.status !== 'in_progress' && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setStatus('in_progress')}
                                        data-tour-id="wo-start"
                                    >
                                        Start
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    onClick={() => setStatus('completed')}
                                    data-tour-id="wo-complete"
                                >
                                    Complete
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setStatus('cancelled')}
                                    data-tour-id="wo-cancel"
                                >
                                    Cancel
                                </Button>
                            </>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setStatus('open')}
                                data-tour-id="wo-reopen"
                            >
                                Reopen
                            </Button>
                        )}
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={`/work-orders/${wo.id}/print`}
                                target="_blank"
                                rel="noopener"
                                data-tour-id="wo-print"
                            >
                                Print
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing(true)}
                            data-tour-id="wo-edit"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={remove}
                        >
                            Delete
                        </Button>
                    </div>
                </header>

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="Priority"
                            value={`P${wo.priority} · ${PRIORITY_LABEL[wo.priority] ?? '-'}`}
                        />
                        <Detail
                            label="Scheduled for"
                            value={fmtDateTime(wo.scheduled_for)}
                        />
                        <Detail
                            label="Assignee"
                            value={wo.assignee?.name ?? 'Unassigned'}
                        />
                        <Detail label="Cost" value={money(wo.cost_cents)} />
                        <Detail
                            label="Completed"
                            value={fmtDateTime(wo.completed_at)}
                        />
                        <Detail
                            label="Requested by"
                            value={wo.requester?.name ?? '-'}
                        />
                        <Detail
                            label="From template"
                            value={wo.template?.name ?? '-'}
                        />
                    </CardContent>
                </Card>

                {wo.description ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">
                                Description
                            </h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {wo.description}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                {wo.booking ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">
                                Linked booking
                            </h2>
                            <Link
                                href={bookingShow(wo.booking.id).url}
                                className="text-sm hover:underline"
                            >
                                <span className="font-mono">
                                    {wo.booking.reference}
                                </span>{' '}
                                · {wo.booking.name}
                            </Link>
                        </CardContent>
                    </Card>
                ) : null}

                {wo.exhibitor_source ? (
                    <Card>
                        <CardContent
                            className="flex flex-col gap-2 p-4"
                            data-tour-id="wo-exhibitor-source"
                        >
                            <h2 className="text-sm font-semibold">
                                From exhibitor order
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Auto-generated to set up{' '}
                                <Link
                                    href={`/exhibitors/${wo.exhibitor_source.exhibitor_id}`}
                                    className="text-foreground hover:underline"
                                >
                                    {wo.exhibitor_source.company_name}
                                </Link>
                                {wo.exhibitor_source.booth_assignment
                                    ? ` (booth ${wo.exhibitor_source.booth_assignment})`
                                    : ''}{' '}
                                ·{' '}
                                <Link
                                    href={`/exhibitors/${wo.exhibitor_source.exhibitor_id}/orders/${wo.exhibitor_source.order_id}`}
                                    className="font-mono text-foreground hover:underline"
                                >
                                    {wo.exhibitor_source.order_number}
                                </Link>
                                {wo.department ? ` · ${wo.department}` : ''}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Items</h2>
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {wo.items.length}
                                </span>
                                {isOpen ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setItemEditing(null);
                                            setItemCreating(true);
                                        }}
                                        data-tour-id="wo-add-item"
                                    >
                                        + Add item
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                        {wo.items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No items attached to this work order.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {wo.items.map((i) => (
                                    <li
                                        key={i.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            {isOpen ? (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setItemCreating(false);
                                                        setItemEditing(i);
                                                    }}
                                                    className="text-left font-medium hover:underline"
                                                >
                                                    {i.name}
                                                </button>
                                            ) : (
                                                <div className="font-medium">
                                                    {i.name}
                                                </div>
                                            )}
                                            <div className="text-xs text-muted-foreground">
                                                {i.sku ? (
                                                    <span className="font-mono">
                                                        {i.sku}
                                                    </span>
                                                ) : null}
                                                {i.action
                                                    ? `${i.sku ? ' · ' : ''}${titleCase(i.action)}`
                                                    : ''}
                                                {i.notes ? ` · ${i.notes}` : ''}
                                            </div>
                                        </div>
                                        <span className="shrink-0 text-right text-xs text-muted-foreground">
                                            <div>
                                                {i.quantity ?? '-'}
                                                {i.unit ? ` ${i.unit}` : ''}
                                            </div>
                                            {i.unit_cost_cents != null ? (
                                                <div className="font-mono">
                                                    {money(
                                                        i.unit_cost_cents *
                                                            (i.quantity ?? 1),
                                                    )}
                                                </div>
                                            ) : null}
                                        </span>
                                        {isOpen ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="shrink-0 text-rose-600 dark:text-rose-400"
                                                onClick={() => removeItem(i)}
                                            >
                                                Remove
                                            </Button>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {itemsSubtotal > 0 ? (
                            <div className="flex justify-between border-t border-border pt-2 text-sm">
                                <span className="text-muted-foreground">
                                    Items subtotal
                                </span>
                                <span className="font-mono font-medium">
                                    {money(itemsSubtotal)}
                                </span>
                            </div>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function WorkOrderItemModal({
    workOrderId,
    item,
    actions,
    resources,
    open,
    onClose,
}: {
    workOrderId: number;
    item: Item | null;
    actions: ItemAction[];
    resources: ResourceOption[];
    open: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent>
                {open ? (
                    <WorkOrderItemForm
                        key={item?.id ?? 'new'}
                        workOrderId={workOrderId}
                        item={item}
                        actions={actions}
                        resources={resources}
                        onClose={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function WorkOrderItemForm({
    workOrderId,
    item,
    actions,
    resources,
    onClose,
}: {
    workOrderId: number;
    item: Item | null;
    actions: ItemAction[];
    resources: ResourceOption[];
    onClose: () => void;
}) {
    const isEdit = item !== null;
    const [form, setForm] = useState({
        resource_inventory_id: item?.resource_inventory_id
            ? String(item.resource_inventory_id)
            : '',
        name: item?.name ?? '',
        sku: item?.sku ?? '',
        quantity: item?.quantity ?? 1,
        unit: item?.unit ?? '',
        unit_cost:
            item?.unit_cost_cents != null
                ? String(item.unit_cost_cents / 100)
                : '',
        action: item?.action ?? actions[0]?.value ?? 'consume',
        notes: item?.notes ?? '',
    });
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const pickResource = (id: string) => {
        const r = resources.find((x) => String(x.id) === id);
        setForm((f) => ({
            ...f,
            resource_inventory_id: id,
            ...(r ? { name: r.name, sku: r.sku ?? '' } : {}),
        }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const payload = {
            resource_inventory_id: form.resource_inventory_id
                ? Number(form.resource_inventory_id)
                : null,
            name: form.name,
            sku: form.sku || null,
            quantity: Number(form.quantity),
            unit: form.unit || null,
            unit_cost_cents:
                form.unit_cost === ''
                    ? null
                    : Math.round(Number(form.unit_cost) * 100),
            action: form.action,
            notes: form.notes || null,
        };
        const opts = {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        };

        if (isEdit) {
            router.patch(`/work-order-items/${item.id}`, payload, opts);
        } else {
            router.post(`/work-orders/${workOrderId}/items`, payload, opts);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>{isEdit ? 'Edit item' : 'Add item'}</DialogTitle>
            </DialogHeader>

            <div className="grid gap-3 sm:grid-cols-2">
                {resources.length > 0 ? (
                    <div className="grid gap-1.5 sm:col-span-2">
                        <Label htmlFor="wi-resource">
                            From inventory (optional)
                        </Label>
                        <select
                            id="wi-resource"
                            value={form.resource_inventory_id}
                            onChange={(e) => pickResource(e.target.value)}
                            className={selectClass}
                        >
                            <option value="">- Free text -</option>
                            {resources.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name}
                                    {r.sku ? ` (${r.sku})` : ''} ·{' '}
                                    {r.quantity_available} avail
                                </option>
                            ))}
                        </select>
                        <p className="text-[11px] text-muted-foreground">
                            Picking a resource fills the name + SKU and links
                            the item so stock adjusts when the order completes.
                        </p>
                    </div>
                ) : null}

                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="wi-name">Name</Label>
                    <Input
                        id="wi-name"
                        value={form.name}
                        onChange={(e) => set('name', e.target.value)}
                        required
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wi-sku">SKU (optional)</Label>
                    <Input
                        id="wi-sku"
                        value={form.sku}
                        onChange={(e) => set('sku', e.target.value)}
                        className="font-mono"
                    />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="wi-qty">Quantity</Label>
                        <Input
                            id="wi-qty"
                            type="number"
                            min={1}
                            value={form.quantity}
                            onChange={(e) =>
                                set('quantity', Number(e.target.value) || 1)
                            }
                            required
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="wi-unit">Unit</Label>
                        <Input
                            id="wi-unit"
                            value={form.unit}
                            onChange={(e) => set('unit', e.target.value)}
                            placeholder="each"
                        />
                    </div>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wi-cost">Unit cost (USD)</Label>
                    <Input
                        id="wi-cost"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.unit_cost}
                        onChange={(e) => set('unit_cost', e.target.value)}
                        placeholder="-"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wi-action">Action</Label>
                    <select
                        id="wi-action"
                        value={form.action}
                        onChange={(e) => set('action', e.target.value)}
                        className={selectClass}
                    >
                        {actions.map((a) => (
                            <option key={a.value} value={a.value}>
                                {a.label}
                            </option>
                        ))}
                    </select>
                    <p className="text-[11px] text-muted-foreground">
                        Deploy = out · Return = in · Consume = used up · Replace
                        = swap
                    </p>
                </div>
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="wi-notes">Notes (optional)</Label>
                    <textarea
                        id="wi-notes"
                        rows={2}
                        value={form.notes}
                        onChange={(e) => set('notes', e.target.value)}
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={saving}>
                    {isEdit ? 'Save item' : 'Add item'}
                </Button>
            </DialogFooter>
        </form>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-medium">{value}</div>
        </div>
    );
}

function WorkOrderEditModal({
    wo,
    venues,
    kinds,
    assignees,
    open,
    onClose,
}: {
    wo: WorkOrder;
    venues: VenueOption[];
    kinds: KindOption[];
    assignees: Assignee[];
    open: boolean;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                {open ? (
                    <WorkOrderEditForm
                        wo={wo}
                        venues={venues}
                        kinds={kinds}
                        assignees={assignees}
                        onClose={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function WorkOrderEditForm({
    wo,
    venues,
    kinds,
    assignees,
    onClose,
}: {
    wo: WorkOrder;
    venues: VenueOption[];
    kinds: KindOption[];
    assignees: Assignee[];
    onClose: () => void;
}) {
    const [form, setForm] = useState({
        title: wo.title,
        venue_id: wo.venue ? String(wo.venue.id) : '',
        kind: wo.kind ?? '',
        priority: wo.priority,
        scheduled_for: wo.scheduled_for ? wo.scheduled_for.slice(0, 16) : '',
        assigned_to_user_id: wo.assigned_to_user_id
            ? String(wo.assigned_to_user_id)
            : '',
        cost: wo.cost_cents != null ? String(wo.cost_cents / 100) : '',
        description: wo.description ?? '',
    });
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        router.patch(
            `/work-orders/${wo.id}`,
            {
                venue_id: Number(form.venue_id),
                title: form.title,
                description: form.description || null,
                kind: form.kind,
                priority: Number(form.priority),
                scheduled_for: form.scheduled_for || null,
                assigned_to_user_id: form.assigned_to_user_id
                    ? Number(form.assigned_to_user_id)
                    : null,
                cost_cents:
                    form.cost === ''
                        ? null
                        : Math.round(Number(form.cost) * 100),
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>Edit work order</DialogTitle>
                <div className="text-sm text-muted-foreground">
                    <span className="font-mono">{wo.reference}</span>
                </div>
            </DialogHeader>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="wo-e-title">Title</Label>
                    <Input
                        id="wo-e-title"
                        value={form.title}
                        onChange={(e) => set('title', e.target.value)}
                        required
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-venue">Venue</Label>
                    <select
                        id="wo-e-venue"
                        value={form.venue_id}
                        onChange={(e) => set('venue_id', e.target.value)}
                        className={selectClass}
                        required
                    >
                        {venues.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-kind">Kind</Label>
                    <select
                        id="wo-e-kind"
                        value={form.kind}
                        onChange={(e) => set('kind', e.target.value)}
                        className={selectClass}
                        required
                    >
                        {kinds.map((k) => (
                            <option key={k.value} value={k.value}>
                                {k.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-priority">Priority</Label>
                    <select
                        id="wo-e-priority"
                        value={form.priority}
                        onChange={(e) =>
                            set('priority', Number(e.target.value))
                        }
                        className={selectClass}
                    >
                        {PRIORITIES.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-when">Scheduled for</Label>
                    <Input
                        id="wo-e-when"
                        type="datetime-local"
                        value={form.scheduled_for}
                        onChange={(e) => set('scheduled_for', e.target.value)}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-assignee">Assignee</Label>
                    <select
                        id="wo-e-assignee"
                        value={form.assigned_to_user_id}
                        onChange={(e) =>
                            set('assigned_to_user_id', e.target.value)
                        }
                        className={selectClass}
                    >
                        <option value="">- Unassigned -</option>
                        {assignees.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wo-e-cost">Cost (USD)</Label>
                    <Input
                        id="wo-e-cost"
                        type="number"
                        min={0}
                        step="0.01"
                        value={form.cost}
                        onChange={(e) => set('cost', e.target.value)}
                        placeholder="-"
                    />
                </div>
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="wo-e-desc">Description</Label>
                    <textarea
                        id="wo-e-desc"
                        rows={4}
                        value={form.description}
                        onChange={(e) => set('description', e.target.value)}
                        className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={saving}>
                    Save changes
                </Button>
            </DialogFooter>
        </form>
    );
}

WorkOrderShow.layout = {
    breadcrumbs: [{ title: 'Work orders', href: '/work-orders' }],
};
