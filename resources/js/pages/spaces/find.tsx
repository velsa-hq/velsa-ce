import { Form, Head, Link } from '@inertiajs/react';
import { Building2, Search, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useMeasurement } from '@/hooks/use-measurement';
import { show as venueShow } from '@/routes/venues';

type Venue = { id: number; name: string; slug: string };
type Kind = { value: string; label: string };

type Result = {
    id: number;
    name: string;
    venue: { id: number; name: string; slug: string } | null;
    kind: string | null;
    kind_label: string | null;
    capacity: number;
    sqft: number | null;
    bookable_unit: string | null;
    score: number;
    rationale: string;
};

type Criteria = {
    starts_at?: string;
    ends_at?: string;
    attendance?: number;
    min_sqft?: number;
    kind?: string;
    venue_id?: number;
};

type Props = {
    criteria: Criteria;
    results: Result[];
    venues: Venue[];
    kinds: Kind[];
};

export default function SpacesFind({
    criteria,
    results,
    venues,
    kinds,
}: Props) {
    const hasQuery = !!(criteria.starts_at && criteria.ends_at);
    const { unit } = useMeasurement();

    return (
        <>
            <Head title="Find a space · Sales" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Sparkles className="size-6 text-primary" aria-hidden />
                        Find a space
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Enter a date window plus the basics, and we'll return
                        the best-fit available spaces ranked by tightness of
                        fit. Tight fits beat oversized rooms - better
                        utilization for both sides.
                    </p>
                </header>

                <Card>
                    <CardContent className="p-4">
                        <Form
                            method="get"
                            action="/spaces/find"
                            className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            {({ errors }) => (
                                <>
                                    <Field
                                        label="Starts at"
                                        error={errors.starts_at}
                                    >
                                        <input
                                            name="starts_at"
                                            type="datetime-local"
                                            defaultValue={criteria.starts_at}
                                            required
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field
                                        label="Ends at"
                                        error={errors.ends_at}
                                    >
                                        <input
                                            name="ends_at"
                                            type="datetime-local"
                                            defaultValue={criteria.ends_at}
                                            required
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field
                                        label="Attendance"
                                        error={errors.attendance}
                                        hint="Expected headcount"
                                    >
                                        <input
                                            name="attendance"
                                            type="number"
                                            min={1}
                                            defaultValue={criteria.attendance}
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field
                                        label={`Min area (${unit})`}
                                        error={errors.min_sqft}
                                        hint="Optional minimum"
                                    >
                                        <input
                                            name="min_sqft"
                                            type="number"
                                            min={0}
                                            defaultValue={criteria.min_sqft}
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        />
                                    </Field>
                                    <Field
                                        label="Kind"
                                        error={errors.kind}
                                        hint="Optional space type filter"
                                    >
                                        <select
                                            name="kind"
                                            defaultValue={criteria.kind ?? ''}
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">Any kind</option>
                                            {kinds.map((k) => (
                                                <option
                                                    key={k.value}
                                                    value={k.value}
                                                >
                                                    {k.label}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <Field
                                        label="Venue"
                                        error={errors.venue_id}
                                        hint="Optional venue preference"
                                    >
                                        <select
                                            name="venue_id"
                                            defaultValue={
                                                criteria.venue_id
                                                    ? String(criteria.venue_id)
                                                    : ''
                                            }
                                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">Any venue</option>
                                            {venues.map((v) => (
                                                <option
                                                    key={v.id}
                                                    value={String(v.id)}
                                                >
                                                    {v.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                    <div className="sm:col-span-2 lg:col-span-4">
                                        <Button type="submit">
                                            <Search className="size-4" />
                                            Find best fit
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {hasQuery && (
                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">
                                    Results
                                </h2>
                                <span className="text-xs text-muted-foreground">
                                    {results.length}{' '}
                                    {results.length === 1 ? 'space' : 'spaces'}{' '}
                                    available
                                </span>
                            </div>
                            {results.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No available spaces match those criteria.
                                    Try widening the window, relaxing the kind
                                    filter, or lowering the attendance estimate.
                                </p>
                            ) : (
                                <ol className="flex flex-col gap-2">
                                    {results.map((r, idx) => (
                                        <li key={r.id}>
                                            <ResultRow
                                                rank={idx + 1}
                                                result={r}
                                            />
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Field({
    label,
    error,
    hint,
    children,
}: {
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-sm font-medium">{label}</span>
            {children}
            {error ? (
                <span className="text-xs text-rose-600">{error}</span>
            ) : hint ? (
                <span className="text-xs text-muted-foreground">{hint}</span>
            ) : null}
        </label>
    );
}

function ResultRow({ rank, result }: { rank: number; result: Result }) {
    const { formatArea } = useMeasurement();

    return (
        <div className="flex items-center gap-3 rounded-md border border-border p-3">
            <span className="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                {rank}
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="truncate font-medium">{result.name}</span>
                    {result.kind && (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                            {result.kind_label ?? result.kind.replace('_', ' ')}
                        </span>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {result.venue && (
                        <Link
                            href={venueShow(result.venue.slug).url}
                            className="inline-flex items-center gap-1 hover:text-foreground hover:underline"
                        >
                            <Building2 className="size-3" aria-hidden />
                            {result.venue.name}
                        </Link>
                    )}
                    <span>cap {result.capacity.toLocaleString()}</span>
                    {result.sqft ? (
                        <span>{formatArea(result.sqft)}</span>
                    ) : null}
                    {result.bookable_unit ? (
                        <span>{result.bookable_unit}</span>
                    ) : null}
                </div>
                {result.rationale && (
                    <div className="mt-1 text-xs text-muted-foreground italic">
                        {result.rationale}
                    </div>
                )}
            </div>
            <div className="flex flex-col items-end gap-0.5 text-right">
                <span className="text-lg font-semibold tabular-nums">
                    {result.score}
                </span>
                <span className="text-[10px] tracking-wider text-muted-foreground uppercase">
                    fit score
                </span>
            </div>
        </div>
    );
}

SpacesFind.layout = {
    breadcrumbs: [
        { title: 'Sales', href: '/spaces/find' },
        { title: 'Find a space', href: '#' },
    ],
};
