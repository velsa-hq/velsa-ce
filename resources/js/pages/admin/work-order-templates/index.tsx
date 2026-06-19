import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Option = { value: string; label: string };
type Venue = { id: number; name: string };

type TemplateItem = {
    name: string;
    sku: string | null;
    quantity: number;
    unit: string | null;
    unit_cost_cents: number | null;
    action: string;
};

type Recurrence = {
    frequency: 'weekly' | 'monthly';
    interval: number;
    weekday: string;
    monthday: number;
    hour: number;
};

type Template = {
    id: number;
    venue_id: number;
    venue_name: string | null;
    name: string;
    kind: string | null;
    lookahead_days: number;
    default_assignee_role: string | null;
    is_active: boolean;
    cadence: string;
    recurrence: Recurrence;
    items: TemplateItem[];
    generated_count: number;
};

type Props = {
    templates: Template[];
    venues: Venue[];
    kinds: Option[];
    actions: Option[];
    filters: { venue_id: number | null };
};

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

const WEEKDAYS: Option[] = [
    { value: 'MO', label: 'Monday' },
    { value: 'TU', label: 'Tuesday' },
    { value: 'WE', label: 'Wednesday' },
    { value: 'TH', label: 'Thursday' },
    { value: 'FR', label: 'Friday' },
    { value: 'SA', label: 'Saturday' },
    { value: 'SU', label: 'Sunday' },
];

export default function WorkOrderTemplatesIndex({
    templates,
    venues,
    kinds,
    actions,
    filters,
}: Props) {
    const [editing, setEditing] = useState<Template | null>(null);
    const [creating, setCreating] = useState(false);

    const onVenueFilter = (value: string) =>
        router.get(
            '/admin/work-order-templates',
            value ? { venue_id: value } : {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );

    const remove = (t: Template) => {
        if (!window.confirm(`Delete recurring template "${t.name}"?`)) {
            return;
        }

        router.delete(`/admin/work-order-templates/${t.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Recurring work orders · Admin" />

            <TemplateModal
                open={creating || editing !== null}
                template={editing}
                venues={venues}
                kinds={kinds}
                actions={actions}
                defaultVenueId={filters.venue_id}
                onClose={() => {
                    setCreating(false);
                    setEditing(null);
                }}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Recurring work orders
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Preventive-maintenance templates. Active templates
                        auto-generate real work orders on their cadence (the
                        nightly <code>workorders:materialize</code> job),
                        looking ahead the configured number of days.
                    </p>
                </header>

                <div className="flex items-center justify-between gap-2">
                    <select
                        value={filters.venue_id ?? ''}
                        onChange={(e) => onVenueFilter(e.target.value)}
                        className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                        aria-label="Filter by venue"
                    >
                        <option value="">All venues</option>
                        {venues.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name}
                            </option>
                        ))}
                    </select>
                    <Button
                        size="sm"
                        onClick={() => {
                            setEditing(null);
                            setCreating(true);
                        }}
                        data-tour-id="wot-add"
                    >
                        + Add template
                    </Button>
                </div>

                <div className="flex flex-col gap-3">
                    {templates.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No recurring templates yet.
                        </p>
                    ) : (
                        templates.map((t) => (
                            <div
                                key={t.id}
                                className={`flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border ${t.is_active ? '' : 'opacity-60'}`}
                            >
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setCreating(false);
                                                setEditing(t);
                                            }}
                                            className="text-left font-medium hover:underline"
                                        >
                                            {t.name}
                                        </button>
                                        {!t.is_active ? (
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                inactive
                                            </span>
                                        ) : null}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {t.venue_name} ·{' '}
                                        {t.kind?.replace(/_/g, ' ')} ·{' '}
                                        {t.cadence} · {t.items.length} item
                                        {t.items.length === 1 ? '' : 's'} ·{' '}
                                        {t.generated_count} generated
                                    </div>
                                </div>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setCreating(false);
                                            setEditing(t);
                                        }}
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-rose-600 dark:text-rose-400"
                                        onClick={() => remove(t)}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

function TemplateModal(props: {
    open: boolean;
    template: Template | null;
    venues: Venue[];
    kinds: Option[];
    actions: Option[];
    defaultVenueId: number | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={props.open}
            onOpenChange={(next) => {
                if (!next) {
                    props.onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                {props.open ? (
                    <TemplateForm
                        key={props.template?.id ?? 'new'}
                        {...props}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function TemplateForm({
    template,
    venues,
    kinds,
    actions,
    defaultVenueId,
    onClose,
}: {
    template: Template | null;
    venues: Venue[];
    kinds: Option[];
    actions: Option[];
    defaultVenueId: number | null;
    onClose: () => void;
}) {
    const isEdit = template !== null;
    const r = template?.recurrence;
    const [form, setForm] = useState({
        venue_id: String(
            template?.venue_id ?? defaultVenueId ?? venues[0]?.id ?? '',
        ),
        name: template?.name ?? '',
        kind: template?.kind ?? kinds[0]?.value ?? '',
        frequency: r?.frequency ?? 'weekly',
        interval: r?.interval ?? 1,
        weekday: r?.weekday ?? 'MO',
        monthday: r?.monthday ?? 1,
        hour: r?.hour ?? 8,
        lookahead_days: template?.lookahead_days ?? 14,
        default_assignee_role: template?.default_assignee_role ?? '',
        is_active: template?.is_active ?? true,
    });
    const [items, setItems] = useState<TemplateItem[]>(template?.items ?? []);
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const setItem = (idx: number, patch: Partial<TemplateItem>) =>
        setItems((list) =>
            list.map((it, i) => (i === idx ? { ...it, ...patch } : it)),
        );

    const addItem = () =>
        setItems((list) => [
            ...list,
            {
                name: '',
                sku: null,
                quantity: 1,
                unit: 'each',
                unit_cost_cents: null,
                action: actions[0]?.value ?? 'consume',
            },
        ]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const payload = {
            venue_id: Number(form.venue_id),
            name: form.name,
            kind: form.kind,
            frequency: form.frequency,
            interval: Number(form.interval),
            weekday: form.weekday,
            monthday: Number(form.monthday),
            hour: Number(form.hour),
            lookahead_days: Number(form.lookahead_days),
            default_assignee_role: form.default_assignee_role || null,
            is_active: form.is_active,
            items: items
                .filter((it) => it.name.trim() !== '')
                .map((it) => ({
                    name: it.name,
                    sku: it.sku || null,
                    quantity: Number(it.quantity),
                    unit: it.unit || null,
                    unit_cost_cents: it.unit_cost_cents,
                    action: it.action,
                })),
        };
        const opts = {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        };

        if (isEdit) {
            router.put(
                `/admin/work-order-templates/${template.id}`,
                payload,
                opts,
            );
        } else {
            router.post('/admin/work-order-templates', payload, opts);
        }
    };

    return (
        <form
            onSubmit={submit}
            className="flex max-h-[80vh] flex-col gap-4 overflow-y-auto"
        >
            <DialogHeader>
                <DialogTitle>
                    {isEdit
                        ? 'Edit recurring template'
                        : 'New recurring template'}
                </DialogTitle>
            </DialogHeader>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="wot-name">Name</Label>
                    <Input
                        id="wot-name"
                        value={form.name}
                        onChange={(e) => set('name', e.target.value)}
                        placeholder="e.g. Weekly HVAC filter check"
                        required
                        data-tour-id="wot-name"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wot-venue">Venue</Label>
                    <select
                        id="wot-venue"
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
                    <Label htmlFor="wot-kind">Kind</Label>
                    <select
                        id="wot-kind"
                        value={form.kind}
                        onChange={(e) => set('kind', e.target.value)}
                        className={selectClass}
                    >
                        {kinds.map((k) => (
                            <option key={k.value} value={k.value}>
                                {k.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="rounded-lg border border-dashed border-border p-3">
                <div className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    Cadence
                </div>
                <div className="grid gap-3 sm:grid-cols-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="wot-freq">Repeat</Label>
                        <select
                            id="wot-freq"
                            value={form.frequency}
                            onChange={(e) =>
                                set(
                                    'frequency',
                                    e.target.value as 'weekly' | 'monthly',
                                )
                            }
                            className={selectClass}
                        >
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="wot-interval">Every</Label>
                        <Input
                            id="wot-interval"
                            type="number"
                            min={1}
                            max={99}
                            value={form.interval}
                            onChange={(e) =>
                                set('interval', Number(e.target.value) || 1)
                            }
                        />
                    </div>
                    {form.frequency === 'weekly' ? (
                        <div className="grid gap-1.5">
                            <Label htmlFor="wot-weekday">On</Label>
                            <select
                                id="wot-weekday"
                                value={form.weekday}
                                onChange={(e) => set('weekday', e.target.value)}
                                className={selectClass}
                            >
                                {WEEKDAYS.map((d) => (
                                    <option key={d.value} value={d.value}>
                                        {d.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : (
                        <div className="grid gap-1.5">
                            <Label htmlFor="wot-monthday">Day of month</Label>
                            <Input
                                id="wot-monthday"
                                type="number"
                                min={1}
                                max={28}
                                value={form.monthday}
                                onChange={(e) =>
                                    set('monthday', Number(e.target.value) || 1)
                                }
                            />
                        </div>
                    )}
                    <div className="grid gap-1.5">
                        <Label htmlFor="wot-hour">Hour (0-23)</Label>
                        <Input
                            id="wot-hour"
                            type="number"
                            min={0}
                            max={23}
                            value={form.hour}
                            onChange={(e) =>
                                set('hour', Number(e.target.value))
                            }
                        />
                    </div>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="wot-lookahead">Look-ahead days</Label>
                    <Input
                        id="wot-lookahead"
                        type="number"
                        min={1}
                        max={90}
                        value={form.lookahead_days}
                        onChange={(e) =>
                            set('lookahead_days', Number(e.target.value) || 14)
                        }
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="wot-role">Default assignee role</Label>
                    <Input
                        id="wot-role"
                        value={form.default_assignee_role}
                        onChange={(e) =>
                            set('default_assignee_role', e.target.value)
                        }
                        placeholder="e.g. ops_lead"
                    />
                </div>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                    <input
                        type="checkbox"
                        checked={form.is_active}
                        onChange={(e) => set('is_active', e.target.checked)}
                        className="size-4 rounded border-border accent-primary"
                    />
                    Active (auto-generates work orders)
                </label>
            </div>

            <div className="grid gap-2">
                <div className="flex items-center justify-between">
                    <Label>Materials each generated order gets</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addItem}
                    >
                        + Add item
                    </Button>
                </div>
                {items.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        No materials - the generated work orders will have no
                        items.
                    </p>
                ) : (
                    items.map((it, idx) => (
                        <div
                            key={idx}
                            className="grid grid-cols-12 items-end gap-2 rounded-md border border-border p-2"
                        >
                            <div className="col-span-4 grid gap-1">
                                <Label className="text-[10px]">Name</Label>
                                <Input
                                    value={it.name}
                                    onChange={(e) =>
                                        setItem(idx, { name: e.target.value })
                                    }
                                    className="h-8"
                                />
                            </div>
                            <div className="col-span-2 grid gap-1">
                                <Label className="text-[10px]">Qty</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={it.quantity}
                                    onChange={(e) =>
                                        setItem(idx, {
                                            quantity:
                                                Number(e.target.value) || 1,
                                        })
                                    }
                                    className="h-8"
                                />
                            </div>
                            <div className="col-span-2 grid gap-1">
                                <Label className="text-[10px]">Unit</Label>
                                <Input
                                    value={it.unit ?? ''}
                                    onChange={(e) =>
                                        setItem(idx, { unit: e.target.value })
                                    }
                                    className="h-8"
                                />
                            </div>
                            <div className="col-span-3 grid gap-1">
                                <Label className="text-[10px]">Action</Label>
                                <select
                                    value={it.action}
                                    onChange={(e) =>
                                        setItem(idx, { action: e.target.value })
                                    }
                                    className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                                >
                                    {actions.map((a) => (
                                        <option key={a.value} value={a.value}>
                                            {a.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    setItems((list) =>
                                        list.filter((_, i) => i !== idx),
                                    )
                                }
                                className="col-span-1 pb-2 text-rose-500"
                                aria-label="Remove item"
                            >
                                x
                            </button>
                        </div>
                    ))
                )}
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={saving}>
                    {isEdit ? 'Save template' : 'Add template'}
                </Button>
            </DialogFooter>
        </form>
    );
}

WorkOrderTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '#' },
        { title: 'Recurring work orders', href: '/admin/work-order-templates' },
    ],
};
