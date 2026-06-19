import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { fmtWallDateTime, wallClock } from '@/lib/datetime';
import {
    create as bookingsCreate,
    show as bookingShow,
} from '@/routes/bookings';
import {
    archive as archiveLead,
    clone as cloneLead,
    edit,
    reopen as reopenLead,
} from '@/routes/leads';
import {
    store as storeActivity,
    toggle as toggleActivity,
} from '@/routes/leads/activities';

type Activity = {
    id: number;
    kind: string;
    summary: string;
    note: string | null;
    due_at: string | null;
    completed_at: string | null;
    is_overdue: boolean;
    user: { id: number; name: string; email: string } | null;
};

type Lead = {
    id: number;
    name: string;
    stage: string;
    estimated_value_cents: number;
    probability: number;
    weighted_value_cents: number;
    expected_close_date: string | null;
    source: string | null;
    lost_reason: string | null;
    notes: string | null;
    closed_at: string | null;
    converted_at: string | null;
    client: { id: number; name: string; type: string | null } | null;
    venue: { id: number; name: string; slug: string } | null;
    owner: { id: number; name: string; email: string } | null;
    converted_booking: { id: number; reference: string } | null;
};

type Props = {
    lead: Lead;
    activities: Activity[];
    activity_kinds: string[];
    stage_labels: Record<string, string>;
};

const STAGE_COLORS: Record<string, string> = {
    new: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    qualified: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    proposal_sent:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    contract_sent:
        'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    won: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    lost: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

const KIND_LABELS: Record<string, string> = {
    call: 'Call',
    email: 'Email',
    meeting: 'Meeting',
    task: 'Task',
    site_visit: 'Site visit',
    note: 'Note',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function LeadsShow({
    lead,
    activities,
    activity_kinds,
    stage_labels,
}: Props) {
    const [toggling, setToggling] = useState<number | null>(null);

    const onToggle = (activity: Activity) => {
        router.patch(
            toggleActivity({ lead: lead.id, activity: activity.id }).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setToggling(activity.id),
                onFinish: () => setToggling(null),
            },
        );
    };

    const open = activities.filter((a) => a.completed_at === null);
    const completed = activities.filter((a) => a.completed_at !== null);

    const isTerminal = lead.stage === 'won' || lead.stage === 'lost';
    const canReopen = isTerminal && lead.converted_booking === null;

    const onReopen = () =>
        router.patch(reopenLead(lead.id).url, {}, { preserveScroll: true });
    const onClone = () => router.post(cloneLead(lead.id).url);
    const onArchive = () =>
        router.patch(archiveLead(lead.id).url, {}, { preserveScroll: true });

    return (
        <>
            <Head title={`${lead.name} · Lead`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {lead.name}
                            </h1>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STAGE_COLORS[lead.stage] ?? ''}`}
                            >
                                {stage_labels[lead.stage] ?? lead.stage}
                            </span>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {lead.client ? (
                                <Link
                                    href={`/clients/${lead.client.id}`}
                                    className="hover:underline"
                                >
                                    {lead.client.name}
                                </Link>
                            ) : (
                                '-'
                            )}
                            {lead.venue ? (
                                <>
                                    {' · '}
                                    <Link
                                        href={`/venues/${lead.venue.slug}`}
                                        className="hover:underline"
                                    >
                                        {lead.venue.name}
                                    </Link>
                                </>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(lead.id).url}>Edit</Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onClone}
                            data-tour-id="lead-clone"
                        >
                            Clone
                        </Button>
                        {canReopen ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onReopen}
                                data-tour-id="lead-reopen"
                            >
                                Reopen
                            </Button>
                        ) : null}
                        {isTerminal ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onArchive}
                                data-tour-id="lead-archive"
                            >
                                Archive
                            </Button>
                        ) : null}
                        {lead.stage === 'won' &&
                        lead.converted_booking === null ? (
                            <Button asChild size="sm">
                                <Link
                                    href={`${bookingsCreate().url}?from_lead=${lead.id}`}
                                    data-tour-id="lead-convert"
                                >
                                    + Convert to booking
                                </Link>
                            </Button>
                        ) : null}
                        {lead.converted_booking ? (
                            <Link
                                href={
                                    bookingShow(lead.converted_booking.id).url
                                }
                                className="rounded-md border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-900 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200"
                            >
                                ✓ {lead.converted_booking.reference}
                            </Link>
                        ) : null}
                    </div>
                </header>

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="Estimated value"
                            value={
                                lead.estimated_value_cents
                                    ? money(lead.estimated_value_cents)
                                    : '-'
                            }
                        />
                        <Detail
                            label="Weighted"
                            value={money(lead.weighted_value_cents)}
                            sub={`${Math.round(lead.probability * 100)}% probability`}
                        />
                        <Detail
                            label="Expected close"
                            value={formatDate(lead.expected_close_date)}
                        />
                        <Detail label="Source" value={lead.source ?? '-'} />
                        <Detail
                            label="Owner"
                            value={lead.owner?.name ?? '-'}
                            sub={lead.owner?.email ?? null}
                        />
                        {lead.stage === 'lost' ? (
                            <Detail
                                label="Lost reason"
                                value={lead.lost_reason ?? '-'}
                            />
                        ) : null}
                        {lead.closed_at ? (
                            <Detail
                                label="Closed at"
                                value={formatDateTime(lead.closed_at)}
                            />
                        ) : null}
                    </CardContent>
                </Card>

                {lead.notes ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">Notes</h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {lead.notes}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">
                                    Activities
                                </h2>
                                <span className="text-xs text-muted-foreground">
                                    {open.length} open · {completed.length} done
                                </span>
                            </div>

                            {activities.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No activities yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {open.map((a) => (
                                        <ActivityItem
                                            key={a.id}
                                            activity={a}
                                            toggling={toggling === a.id}
                                            onToggle={() => onToggle(a)}
                                        />
                                    ))}
                                    {completed.length > 0 && open.length > 0 ? (
                                        <li className="my-1 border-t border-border" />
                                    ) : null}
                                    {completed.map((a) => (
                                        <ActivityItem
                                            key={a.id}
                                            activity={a}
                                            toggling={toggling === a.id}
                                            onToggle={() => onToggle(a)}
                                        />
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <h2 className="text-sm font-semibold">
                                Add an activity
                            </h2>
                            <Form
                                {...storeActivity.form(lead.id)}
                                resetOnSuccess
                                className="flex flex-col gap-3"
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="kind">Kind</Label>
                                            <select
                                                id="kind"
                                                name="kind"
                                                required
                                                defaultValue="call"
                                                className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                            >
                                                {activity_kinds.map((k) => (
                                                    <option key={k} value={k}>
                                                        {KIND_LABELS[k] ?? k}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="summary">
                                                Summary
                                            </Label>
                                            <Input
                                                id="summary"
                                                name="summary"
                                                type="text"
                                                required
                                                placeholder="Discovery call with client"
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="due_at">
                                                Due (optional)
                                            </Label>
                                            <Input
                                                id="due_at"
                                                name="due_at"
                                                type="datetime-local"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            Add activity
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Link
                        href="/pipeline"
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to pipeline
                    </Link>
                </div>
            </div>
        </>
    );
}

function ActivityItem({
    activity,
    toggling,
    onToggle,
}: {
    activity: Activity;
    toggling: boolean;
    onToggle: () => void;
}) {
    return (
        <li className="flex items-start gap-3 rounded-md border border-border p-2">
            <button
                type="button"
                onClick={onToggle}
                disabled={toggling}
                role="switch"
                aria-checked={activity.completed_at !== null}
                aria-label={`Mark "${activity.summary}" as ${activity.completed_at ? 'not done' : 'done'}`}
                className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border ${
                    activity.completed_at
                        ? 'border-emerald-500 bg-emerald-500 text-white'
                        : 'border-border hover:border-foreground'
                }`}
            >
                <span aria-hidden>{activity.completed_at ? '✓' : ''}</span>
            </button>
            <div className="flex-1">
                <div className="flex items-baseline gap-2">
                    <span className="text-[10px] tracking-wide text-muted-foreground uppercase">
                        {KIND_LABELS[activity.kind] ?? activity.kind}
                    </span>
                    <span
                        className={
                            activity.completed_at
                                ? 'text-sm text-muted-foreground line-through'
                                : 'text-sm'
                        }
                    >
                        {activity.summary}
                    </span>
                </div>
                <div className="text-xs text-muted-foreground">
                    {activity.due_at ? (
                        <>
                            due {fmtWallDateTime(activity.due_at)}
                            {activity.is_overdue ? (
                                <span className="ml-1 font-medium text-rose-700 dark:text-rose-300">
                                    · OVERDUE
                                </span>
                            ) : null}
                        </>
                    ) : (
                        'no due date'
                    )}
                    {activity.user ? <> · {activity.user.name}</> : null}
                </div>
            </div>
        </li>
    );
}

function Detail({
    label,
    value,
    sub,
}: {
    label: string;
    value: string;
    sub?: string | null;
}) {
    return (
        <div>
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-medium">{value}</div>
            {sub ? (
                <div className="text-xs text-muted-foreground">{sub}</div>
            ) : null}
        </div>
    );
}

LeadsShow.layout = {
    breadcrumbs: [{ title: 'Pipeline', href: '/pipeline' }],
};
