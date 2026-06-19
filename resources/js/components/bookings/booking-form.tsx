import { Form, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Space = {
    id: number;
    parent_space_id: number | null;
    name: string;
    kind: string | null;
    capacity: number | null;
};
export type VenueOption = {
    id: number;
    name: string;
    slug: string;
    spaces: Space[];
};
export type ClientOption = { id: number; name: string };

export type BookingFormInitial = {
    venue_id: number | '';
    client_id: number | '';
    name: string;
    kind: string;
    status: string;
    start_at: string;
    end_at: string;
    attendance_estimate: string;
    total_dollars: string;
    notes: string;
    spaces: number[];
    cancel_reason: string;
};

type Props = {
    formAction: {
        method: 'post' | 'put' | 'patch' | 'delete' | 'get';
        action: string;
    };
    initial: BookingFormInitial;
    venues: VenueOption[];
    clients: ClientOption[];
    kinds: { value: string; label: string }[];
    statuses: string[];
    clientTypes?: string[];
    allowNewClient?: boolean;
    showCancelReason?: boolean;
    leadId?: number | null;
    submitLabel: string;
    cancelHref: string;
};

const STATUS_LABELS: Record<string, string> = {
    inquiry: 'Inquiry',
    hold: 'Hold',
    tentative: 'Tentative',
    definite: 'Definite',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const CLIENT_TYPE_LABELS: Record<string, string> = {
    individual: 'Individual',
    business: 'Business',
    government: 'Government',
    nonprofit: 'Nonprofit',
    educational: 'Educational',
};

/**
 * Flatten spaces into tree-order with depth, for an indented list (parents
 * above children). Parentless spaces and orphans are treated as roots.
 */
function orderTree(spaces: Space[]): Array<{ space: Space; depth: number }> {
    const byParent = new Map<number | null, Space[]>();

    for (const s of spaces) {
        const key = s.parent_space_id ?? null;

        if (!byParent.has(key)) {
            byParent.set(key, []);
        }

        byParent.get(key)!.push(s);
    }

    const knownIds = new Set(spaces.map((s) => s.id));
    const out: Array<{ space: Space; depth: number }> = [];

    function walk(parentId: number | null, depth: number) {
        const kids = byParent.get(parentId) ?? [];

        for (const s of kids) {
            out.push({ space: s, depth });
            walk(s.id, depth + 1);
        }
    }

    walk(null, 0);

    // defensive: catch orphans whose parent isn't in the same venue
    for (const s of spaces) {
        if (out.some((row) => row.space.id === s.id)) {
            continue;
        }

        if (s.parent_space_id !== null && !knownIds.has(s.parent_space_id)) {
            out.push({ space: s, depth: 0 });
        }
    }

    return out;
}

export function BookingForm({
    formAction,
    initial,
    venues,
    clients,
    kinds,
    statuses,
    clientTypes = [],
    allowNewClient = false,
    showCancelReason = false,
    leadId = null,
    submitLabel,
    cancelHref,
}: Props) {
    const [venueId, setVenueId] = useState<number | ''>(initial.venue_id);
    const [creatingClient, setCreatingClient] = useState(false);
    const [startAt, setStartAt] = useState<string>(initial.start_at);
    const [endAt, setEndAt] = useState<string>(initial.end_at);

    const spaces = useMemo(
        () => venues.find((v) => v.id === venueId)?.spaces ?? [],
        [venues, venueId],
    );

    return (
        <Form
            {...formAction}
            className="flex max-w-3xl flex-col gap-6"
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="name">Event name</Label>
                            <Input
                                id="name"
                                name="name"
                                type="text"
                                required
                                defaultValue={initial.name}
                                placeholder="Spring Wedding Reception"
                                data-tour-id="bf-event-name"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="venue_id">Venue</Label>
                            <select
                                id="venue_id"
                                name="venue_id"
                                required
                                data-tour-id="bf-venue"
                                value={venueId}
                                onChange={(e) =>
                                    setVenueId(
                                        e.target.value
                                            ? Number(e.target.value)
                                            : '',
                                    )
                                }
                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                            >
                                <option value="">Select a venue...</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.venue_id} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center justify-between">
                                <Label
                                    htmlFor={
                                        creatingClient
                                            ? 'new_client_name'
                                            : 'client_id'
                                    }
                                >
                                    Client
                                </Label>
                                {allowNewClient && (
                                    <Button
                                        type="button"
                                        variant="link"
                                        size="sm"
                                        onClick={() =>
                                            setCreatingClient((v) => !v)
                                        }
                                        className="h-auto p-0 text-xs"
                                    >
                                        {creatingClient
                                            ? 'Pick existing'
                                            : '+ New client'}
                                    </Button>
                                )}
                            </div>
                            {creatingClient ? (
                                <div className="grid gap-2">
                                    <Input
                                        id="new_client_name"
                                        name="new_client[name]"
                                        type="text"
                                        required
                                        placeholder="Acme Co"
                                    />
                                    <select
                                        name="new_client[type]"
                                        required
                                        defaultValue="business"
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        aria-label="New client type"
                                    >
                                        {clientTypes.map((t) => (
                                            <option key={t} value={t}>
                                                {CLIENT_TYPE_LABELS[t] ?? t}
                                            </option>
                                        ))}
                                    </select>
                                    <Input
                                        name="new_client[email]"
                                        type="email"
                                        placeholder="primary@example.com (optional)"
                                    />
                                    <InputError
                                        message={errors['new_client.name']}
                                    />
                                    <InputError
                                        message={errors['new_client.type']}
                                    />
                                    <InputError
                                        message={errors['new_client.email']}
                                    />
                                </div>
                            ) : (
                                <>
                                    <select
                                        id="client_id"
                                        name="client_id"
                                        data-tour-id="bf-client"
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        defaultValue={initial.client_id}
                                    >
                                        <option value="">
                                            Select a client...
                                        </option>
                                        {clients.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.client_id} />
                                </>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="kind">Event kind</Label>
                            <select
                                id="kind"
                                name="kind"
                                required
                                data-tour-id="bf-event-kind"
                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                defaultValue={initial.kind}
                            >
                                <option value="">Select a kind...</option>
                                {kinds.map((k) => (
                                    <option key={k.value} value={k.value}>
                                        {k.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.kind} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                required
                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                defaultValue={initial.status}
                            >
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {STATUS_LABELS[s] ?? s}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.status} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="date-range">When</Label>
                            <DateRangePicker
                                id="date-range"
                                data-tour-id="bf-when-range"
                                value={{ startAt, endAt }}
                                onChange={({ startAt: s, endAt: e }) => {
                                    setStartAt(s);
                                    setEndAt(e);
                                }}
                            />
                            <input
                                type="hidden"
                                name="start_at"
                                value={startAt}
                            />
                            {leadId !== null && (
                                <input
                                    type="hidden"
                                    name="lead_id"
                                    value={leadId}
                                />
                            )}
                            <input type="hidden" name="end_at" value={endAt} />
                            <InputError message={errors.start_at} />
                            <InputError message={errors.end_at} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="attendance_estimate">
                                Estimated attendance
                            </Label>
                            <Input
                                id="attendance_estimate"
                                name="attendance_estimate"
                                type="number"
                                min={0}
                                defaultValue={initial.attendance_estimate}
                                placeholder="150"
                            />
                            <InputError message={errors.attendance_estimate} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="total_dollars">
                                Total budget (USD)
                            </Label>
                            <Input
                                id="total_dollars"
                                name="total_dollars"
                                type="number"
                                min={0}
                                step={0.01}
                                defaultValue={initial.total_dollars}
                                placeholder="1500.00"
                            />
                            <InputError message={errors.total_dollars} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="notes">Notes</Label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows={3}
                                defaultValue={initial.notes}
                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                placeholder="Internal notes about this booking..."
                            />
                            <InputError message={errors.notes} />
                        </div>

                        {showCancelReason && (
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="cancel_reason">
                                    Cancellation reason
                                </Label>
                                <textarea
                                    id="cancel_reason"
                                    name="cancel_reason"
                                    rows={2}
                                    defaultValue={initial.cancel_reason}
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    placeholder="Only used when status is Cancelled."
                                />
                                <InputError message={errors.cancel_reason} />
                            </div>
                        )}
                    </div>

                    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-4">
                        <legend className="text-sm font-medium">Spaces</legend>
                        {venueId === '' ? (
                            <p className="text-sm text-muted-foreground">
                                Pick a venue above to see its available spaces.
                            </p>
                        ) : spaces.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                This venue has no spaces configured.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-1.5">
                                <p className="text-xs text-muted-foreground">
                                    Indented spaces are sub-spaces of the one
                                    above. To book a parent with any of its
                                    sub-spaces, you must include all of them.
                                </p>
                                {orderTree(spaces).map(
                                    ({ space: s, depth }) => (
                                        <label
                                            key={s.id}
                                            htmlFor={`space-${s.id}`}
                                            style={{
                                                marginLeft: `${depth * 24}px`,
                                            }}
                                            className="flex items-center gap-2 rounded-md border border-border p-2 text-sm has-[:checked]:border-primary has-[:checked]:bg-muted"
                                        >
                                            <input
                                                id={`space-${s.id}`}
                                                type="checkbox"
                                                name="spaces[]"
                                                value={s.id}
                                                defaultChecked={initial.spaces.includes(
                                                    s.id,
                                                )}
                                                aria-label={s.name}
                                                className="size-4 rounded border-border accent-primary"
                                            />
                                            <span className="flex-1">
                                                <span className="block font-medium">
                                                    {s.name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {s.kind?.replace(
                                                        '_',
                                                        ' ',
                                                    ) ?? ''}
                                                    {s.capacity
                                                        ? ` · cap ${s.capacity}`
                                                        : ''}
                                                </span>
                                            </span>
                                        </label>
                                    ),
                                )}
                            </div>
                        )}
                        <InputError message={errors.spaces} />
                    </fieldset>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-tour-id="bf-save-booking"
                        >
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={cancelHref}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
