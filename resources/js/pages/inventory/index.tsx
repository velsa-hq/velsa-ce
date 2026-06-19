import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import HelpLink from '@/components/help-link';
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

type Resource = {
    id: number;
    venue_id: number;
    venue_name: string | null;
    kind: string;
    kind_label: string;
    sku: string | null;
    name: string;
    quantity_total: number;
    quantity_available: number;
    reorder_point: number;
    is_consumable: boolean;
    is_low: boolean;
};

type Venue = { id: number; name: string };
type KindOption = { value: string; label: string };

type Props = {
    resources: Resource[];
    venues: Venue[];
    kinds: KindOption[];
    filters: {
        venue_id: number | null;
        low_only: boolean;
        type: string | null;
    };
    low_count: number;
};

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

export default function InventoryIndex({
    resources,
    venues,
    kinds,
    filters,
    low_count,
}: Props) {
    const [editing, setEditing] = useState<Resource | null>(null);
    const [creating, setCreating] = useState(false);

    const applyFilters = (next: {
        venue_id?: string;
        low_only?: boolean;
        type?: string;
    }) => {
        const params: Record<string, string> = {};
        const venueId = next.venue_id ?? (filters.venue_id?.toString() || '');
        const lowOnly = next.low_only ?? filters.low_only;
        const type = next.type ?? (filters.type || '');

        if (venueId) {
            params.venue_id = venueId;
        }

        if (lowOnly) {
            params.low_only = '1';
        }

        if (type) {
            params.type = type;
        }

        router.get('/inventory', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const onVenueFilter = (value: string) => applyFilters({ venue_id: value });

    const sheetParams = new URLSearchParams({
        ...(filters.venue_id ? { venue_id: String(filters.venue_id) } : {}),
        ...(filters.type ? { type: filters.type } : {}),
        ...(filters.low_only ? { low_only: '1' } : {}),
    }).toString();
    const printSheetHref = `/inventory/print${sheetParams ? `?${sheetParams}` : ''}`;

    const retire = (r: Resource) => {
        if (!window.confirm(`Retire "${r.name}"? It leaves the active list.`)) {
            return;
        }

        router.delete(`/inventory/${r.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Inventory" />

            <InventoryModal
                open={creating || editing !== null}
                resource={editing}
                venues={venues}
                kinds={kinds}
                defaultVenueId={filters.venue_id}
                onClose={() => {
                    setCreating(false);
                    setEditing(null);
                }}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Inventory
                            <HelpLink slug="operations/inventory" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {resources.length} resource
                            {resources.length === 1 ? '' : 's'} · deployable
                            stock per venue
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/inventory/activity">Activity</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={printSheetHref}
                                target="_blank"
                                rel="noopener"
                                data-tour-id="inventory-print"
                            >
                                Print sheet
                            </a>
                        </Button>
                        <Button
                            variant={filters.low_only ? 'default' : 'outline'}
                            size="sm"
                            onClick={() =>
                                applyFilters({ low_only: !filters.low_only })
                            }
                            data-tour-id="inventory-low-filter"
                        >
                            Low stock
                            {low_count > 0 ? ` (${low_count})` : ''}
                        </Button>
                        <select
                            value={filters.type ?? ''}
                            onChange={(e) =>
                                applyFilters({ type: e.target.value })
                            }
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter by type"
                            data-tour-id="inventory-type-filter"
                        >
                            <option value="">All types</option>
                            <option value="consumable">Consumable</option>
                            <option value="durable">Durable</option>
                        </select>
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
                            data-tour-id="inventory-add"
                        >
                            + Add resource
                        </Button>
                    </div>
                </header>

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Resource
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Kind
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Venue
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Available / Total
                                </th>
                                <th className="px-3 py-2 text-right font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {resources.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No resources yet.
                                    </td>
                                </tr>
                            ) : (
                                resources.map((r, idx) => (
                                    <tr
                                        key={r.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setCreating(false);
                                                    setEditing(r);
                                                }}
                                                className="text-left hover:underline"
                                            >
                                                <div className="font-medium">
                                                    {r.name}
                                                </div>
                                                {r.sku ? (
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {r.sku}
                                                    </div>
                                                ) : null}
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {r.kind_label}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {r.venue_name ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap">
                                            <span className="font-mono text-xs">
                                                <span
                                                    className={
                                                        r.quantity_available ===
                                                        0
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : ''
                                                    }
                                                >
                                                    {r.quantity_available}
                                                </span>{' '}
                                                / {r.quantity_total}
                                            </span>
                                            {r.is_low ? (
                                                <span className="ml-2 rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                                                    Reorder
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => retire(r)}
                                                className="text-rose-600 dark:text-rose-400"
                                            >
                                                Retire
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

function InventoryModal({
    open,
    resource,
    venues,
    kinds,
    defaultVenueId,
    onClose,
}: {
    open: boolean;
    resource: Resource | null;
    venues: Venue[];
    kinds: KindOption[];
    defaultVenueId: number | null;
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
                    <InventoryForm
                        key={resource?.id ?? 'new'}
                        resource={resource}
                        venues={venues}
                        kinds={kinds}
                        defaultVenueId={defaultVenueId}
                        onClose={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function InventoryForm({
    resource,
    venues,
    kinds,
    defaultVenueId,
    onClose,
}: {
    resource: Resource | null;
    venues: Venue[];
    kinds: KindOption[];
    defaultVenueId: number | null;
    onClose: () => void;
}) {
    const isEdit = resource !== null;
    const [form, setForm] = useState({
        venue_id: String(
            resource?.venue_id ?? defaultVenueId ?? venues[0]?.id ?? '',
        ),
        name: resource?.name ?? '',
        kind: resource?.kind ?? kinds[0]?.value ?? '',
        sku: resource?.sku ?? '',
        quantity_total: resource?.quantity_total ?? 0,
        quantity_available: resource?.quantity_available ?? 0,
        is_consumable: resource?.is_consumable ?? false,
        reorder_point: resource?.reorder_point ?? 0,
    });
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const payload = {
            venue_id: Number(form.venue_id),
            name: form.name,
            kind: form.kind,
            sku: form.sku || null,
            quantity_total: Number(form.quantity_total),
            quantity_available: Number(form.quantity_available),
            is_consumable: form.is_consumable,
            reorder_point: form.is_consumable ? Number(form.reorder_point) : 0,
        };
        const opts = {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        };

        if (isEdit) {
            router.patch(`/inventory/${resource.id}`, payload, opts);
        } else {
            router.post('/inventory', payload, opts);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <DialogHeader>
                <DialogTitle>
                    {isEdit ? 'Edit resource' : 'Add resource'}
                </DialogTitle>
            </DialogHeader>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="inv-name">Name</Label>
                    <Input
                        id="inv-name"
                        value={form.name}
                        onChange={(e) => set('name', e.target.value)}
                        placeholder="e.g. Stacking chairs"
                        required
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="inv-venue">Venue</Label>
                    <select
                        id="inv-venue"
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
                    <Label htmlFor="inv-kind">Kind</Label>
                    <select
                        id="inv-kind"
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
                    <Label htmlFor="inv-sku">SKU (optional)</Label>
                    <Input
                        id="inv-sku"
                        value={form.sku}
                        onChange={(e) => set('sku', e.target.value)}
                        placeholder="CHR-STD"
                        className="font-mono"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="inv-total">Total quantity</Label>
                    <Input
                        id="inv-total"
                        type="number"
                        min={0}
                        value={form.quantity_total}
                        onChange={(e) =>
                            set('quantity_total', Number(e.target.value))
                        }
                        required
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="inv-avail">Available now</Label>
                    <Input
                        id="inv-avail"
                        type="number"
                        min={0}
                        max={form.quantity_total}
                        value={form.quantity_available}
                        onChange={(e) =>
                            set('quantity_available', Number(e.target.value))
                        }
                        required
                    />
                </div>
                <div className="grid gap-1.5 sm:col-span-2">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.is_consumable}
                            onChange={(e) =>
                                set('is_consumable', e.target.checked)
                            }
                            className="size-4 rounded border-border accent-primary"
                        />
                        Consumable (gets used up + reordered)
                    </label>
                    <p className="text-[11px] text-muted-foreground">
                        Leave off for durable assets that are deployed and
                        returned (chairs, generators, AV...) - they don't
                        reorder.
                    </p>
                </div>
                {form.is_consumable ? (
                    <div className="grid gap-1.5">
                        <Label htmlFor="inv-reorder">Reorder point</Label>
                        <Input
                            id="inv-reorder"
                            type="number"
                            min={0}
                            value={form.reorder_point}
                            onChange={(e) =>
                                set('reorder_point', Number(e.target.value))
                            }
                        />
                        <p className="text-[11px] text-muted-foreground">
                            Flags "reorder" when available drops to this or
                            below. 0 = no alert.
                        </p>
                    </div>
                ) : null}
            </div>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={saving}>
                    {isEdit ? 'Save changes' : 'Add resource'}
                </Button>
            </DialogFooter>
        </form>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: '/inventory' }],
};
