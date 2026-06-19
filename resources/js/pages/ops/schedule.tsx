import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight } from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import { OpsViewSwitcher } from '@/components/ops/ops-view-switcher';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { show as bookingShow } from '@/routes/bookings';
import { show as venueShow } from '@/routes/venues';

type Day = {
    iso: string;
    label_top: string;
    label_bottom: string;
    is_weekend: boolean;
    is_today: boolean;
};

type Booking = {
    id: number;
    reference: string | null;
    name: string | null;
    status: string | null;
    client_name: string | null;
    start_idx: number;
    end_idx: number;
    url: string;
};

type Blackout = {
    id: number;
    reason: string;
    scope: 'venue' | 'space';
    start_idx: number;
    end_idx: number;
};

type Row = {
    id: number;
    name: string;
    kind: string | null;
    capacity: number;
    venue: { id: number; name: string; slug: string } | null;
    bookings: Booking[];
    blackouts: Blackout[];
};

type Venue = { id: number; name: string; slug: string };

type Props = {
    window_start: string;
    prev_week: string;
    next_week: string;
    today: string;
    venue_id: number | null;
    venues: Venue[];
    days: Day[];
    rows: Row[];
};

const STATUS_COLORS: Record<string, string> = {
    hold: 'bg-amber-100 text-amber-900 ring-1 ring-amber-300/40 dark:bg-amber-900/40 dark:text-amber-100',
    tentative:
        'bg-sky-100 text-sky-900 ring-1 ring-sky-300/40 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-300/40 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
        'bg-purple-100 text-purple-900 ring-1 ring-purple-300/40 dark:bg-purple-900/40 dark:text-purple-100',
};

export default function OpsSchedule({
    window_start,
    prev_week,
    next_week,
    today,
    venue_id,
    venues,
    days,
    rows,
}: Props) {
    const navigate = (from: string) => {
        router.visit('/ops/schedule', {
            method: 'get',
            data: {
                from,
                venue_id: venue_id ?? undefined,
            },
            preserveScroll: true,
        });
    };

    const onVenueChange = (value: string) => {
        router.visit('/ops/schedule', {
            method: 'get',
            data: {
                from: window_start,
                venue_id: value || undefined,
            },
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Two-week schedule · Operations" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-2">
                    <OpsViewSwitcher current="schedule" />
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <CalendarClock
                            className="size-6 text-primary"
                            aria-hidden
                        />
                        Two-week schedule
                    </h1>
                </header>

                <Card>
                    <CardContent className="flex flex-wrap items-center gap-3 p-4">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => navigate(prev_week)}
                        >
                            <ChevronLeft className="size-4" />
                            Prev week
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => navigate(today)}
                        >
                            Today
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => navigate(next_week)}
                        >
                            Next week
                            <ChevronRight className="size-4" />
                        </Button>

                        <div className="ml-auto flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">
                                Window: {days[0]?.label_bottom} -{' '}
                                {days[days.length - 1]?.label_bottom}
                            </span>
                            <select
                                value={venue_id ?? ''}
                                onChange={(e) => onVenueChange(e.target.value)}
                                className="rounded-md border border-border bg-background px-3 py-1.5 text-sm"
                                aria-label="Filter by venue"
                            >
                                <option value="">All venues</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={String(v.id)}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-sidebar-border/40 bg-muted/30 px-3 py-2 text-xs dark:border-sidebar-border">
                    <span className="font-medium text-muted-foreground">
                        Status:
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-sm bg-amber-500"
                            aria-hidden
                        />
                        <span className="text-muted-foreground">Hold</span>
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-sm bg-sky-500"
                            aria-hidden
                        />
                        <span className="text-muted-foreground">Tentative</span>
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-sm bg-emerald-500"
                            aria-hidden
                        />
                        <span className="text-muted-foreground">Definite</span>
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-sm bg-purple-500"
                            aria-hidden
                        />
                        <span className="text-muted-foreground">Completed</span>
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <span
                            className="inline-block size-3 rounded-sm bg-rose-500/15 ring-1 ring-rose-400/60"
                            aria-hidden
                        />
                        <span className="text-muted-foreground">Blackout</span>
                    </span>
                </div>

                <Card className="overflow-hidden py-0">
                    <CardContent className="p-0">
                        {rows.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">
                                No spaces match the current filter.
                            </p>
                        ) : (
                            <ScheduleGrid days={days} rows={rows} />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

const SCHEDULE_LABEL_WIDTH_REM = 14;
const SCHEDULE_DAY_WIDTH_REM = 7;
const SCHEDULE_BAR_INSET_REM = 0.25;

// per-venue accent keyed by venue id; literal class strings so Tailwind keeps them
const VENUE_TINTS = [
    {
        band: 'bg-blue-50/70 dark:bg-blue-950/30',
        accent: 'border-l-blue-400',
        dot: 'bg-blue-400',
        text: 'text-blue-900 dark:text-blue-200',
    },
    {
        band: 'bg-emerald-50/70 dark:bg-emerald-950/30',
        accent: 'border-l-emerald-400',
        dot: 'bg-emerald-400',
        text: 'text-emerald-900 dark:text-emerald-200',
    },
    {
        band: 'bg-amber-50/70 dark:bg-amber-950/30',
        accent: 'border-l-amber-400',
        dot: 'bg-amber-400',
        text: 'text-amber-900 dark:text-amber-200',
    },
    {
        band: 'bg-violet-50/70 dark:bg-violet-950/30',
        accent: 'border-l-violet-400',
        dot: 'bg-violet-400',
        text: 'text-violet-900 dark:text-violet-200',
    },
    {
        band: 'bg-rose-50/70 dark:bg-rose-950/30',
        accent: 'border-l-rose-400',
        dot: 'bg-rose-400',
        text: 'text-rose-900 dark:text-rose-200',
    },
    {
        band: 'bg-cyan-50/70 dark:bg-cyan-950/30',
        accent: 'border-l-cyan-400',
        dot: 'bg-cyan-400',
        text: 'text-cyan-900 dark:text-cyan-200',
    },
];

function venueTint(id: number): (typeof VENUE_TINTS)[number] {
    return VENUE_TINTS[id % VENUE_TINTS.length];
}

function VenueGroupHeader({
    venue,
    dayCount,
}: {
    venue: { id: number; name: string; slug: string };
    dayCount: number;
}) {
    const tint = venueTint(venue.id);

    return (
        <div className="flex" data-tour-id="ops-schedule-venue-group">
            <div
                className={`sticky left-0 z-10 flex items-center gap-2 border-y border-r border-l-4 border-border ${tint.accent} ${tint.band} px-2 py-1.5`}
                style={{
                    width: `${SCHEDULE_LABEL_WIDTH_REM}rem`,
                    flex: '0 0 auto',
                }}
            >
                <span
                    className={`size-2 shrink-0 rounded-full ${tint.dot}`}
                    aria-hidden
                />
                <Link
                    href={venueShow(venue.slug).url}
                    className={`truncate text-xs font-semibold tracking-wider uppercase ${tint.text} hover:underline`}
                >
                    {venue.name}
                </Link>
            </div>
            <div
                className={`border-y border-r border-border ${tint.band}`}
                style={{
                    width: `${dayCount * SCHEDULE_DAY_WIDTH_REM}rem`,
                    flex: '0 0 auto',
                }}
                aria-hidden
            />
        </div>
    );
}

function ScheduleGrid({ days, rows }: { days: Day[]; rows: Row[] }) {
    // app shell is min-height, so cap the scroll pane to the viewport by measuring its offset
    const scrollRef = useRef<HTMLDivElement>(null);
    const [maxHeight, setMaxHeight] = useState<number>();

    useEffect(() => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        const measure = () => {
            setMaxHeight(
                window.innerHeight - el.getBoundingClientRect().top - 16,
            );
        };

        measure();
        window.addEventListener('resize', measure);

        return () => window.removeEventListener('resize', measure);
    }, []);

    return (
        <div ref={scrollRef} className="overflow-auto" style={{ maxHeight }}>
            <div style={{ minWidth: 'max-content' }}>
                {/* Header */}
                <div className="sticky top-0 z-20 flex bg-background">
                    <div
                        className="sticky left-0 z-30 flex items-center border-r border-b border-border bg-background p-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        style={{
                            width: `${SCHEDULE_LABEL_WIDTH_REM}rem`,
                            flex: '0 0 auto',
                        }}
                    >
                        Space
                    </div>
                    {days.map((d) => (
                        <div
                            key={`h-${d.iso}`}
                            style={{
                                width: `${SCHEDULE_DAY_WIDTH_REM}rem`,
                                flex: '0 0 auto',
                            }}
                            className={`border-r border-b border-border p-1 text-center text-xs ${
                                d.is_weekend ? 'bg-muted/40' : ''
                            } ${
                                d.is_today
                                    ? 'bg-primary/10 text-primary'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            <div className="font-semibold tracking-wider uppercase">
                                {d.label_top}
                            </div>
                            <div className="text-[10px]">{d.label_bottom}</div>
                        </div>
                    ))}
                </div>

                {/* body rows, grouped by venue */}
                {rows.map((row, i) => {
                    const prevVenueId =
                        i > 0 ? (rows[i - 1].venue?.id ?? null) : null;
                    const header =
                        row.venue && row.venue.id !== prevVenueId
                            ? row.venue
                            : null;

                    return (
                        <Fragment key={row.id}>
                            {header && (
                                <VenueGroupHeader
                                    venue={header}
                                    dayCount={days.length}
                                />
                            )}
                            <ScheduleRow row={row} days={days} />
                        </Fragment>
                    );
                })}
            </div>
        </div>
    );
}

function ScheduleRow({ row, days }: { row: Row; days: Day[] }) {
    return (
        <div className="group flex">
            {/* Space label */}
            <div
                className="sticky left-0 z-10 flex flex-col justify-center border-r border-b border-border bg-background px-2 py-1 group-hover:bg-muted/30"
                style={{
                    width: `${SCHEDULE_LABEL_WIDTH_REM}rem`,
                    flex: '0 0 auto',
                }}
            >
                <div className="leading-tight font-medium">{row.name}</div>
                <div className="text-xs leading-tight text-muted-foreground">
                    {[row.kind, row.capacity > 0 ? `cap ${row.capacity}` : null]
                        .filter(Boolean)
                        .join(' · ')}
                </div>
            </div>

            <div
                className="relative flex"
                style={{
                    width: `${days.length * SCHEDULE_DAY_WIDTH_REM}rem`,
                    flex: '0 0 auto',
                }}
            >
                {days.map((d) => (
                    <div
                        key={d.iso}
                        style={{
                            width: `${SCHEDULE_DAY_WIDTH_REM}rem`,
                            flex: '0 0 auto',
                        }}
                        className={`min-h-[3.5rem] border-r border-b border-border ${
                            d.is_weekend ? 'bg-muted/40' : ''
                        } ${d.is_today ? 'bg-primary/5' : ''}`}
                        aria-hidden
                    />
                ))}

                {row.blackouts.map((b) => (
                    <BlackoutBar key={`bl-${b.id}`} blackout={b} />
                ))}
                {row.bookings.map((b) => (
                    <BookingBar key={`bk-${b.id}-${b.start_idx}`} booking={b} />
                ))}
            </div>
        </div>
    );
}

function barPosition(start_idx: number, end_idx: number) {
    const span = end_idx - start_idx + 1;

    return {
        left: `${start_idx * SCHEDULE_DAY_WIDTH_REM + SCHEDULE_BAR_INSET_REM}rem`,
        width: `calc(${span * SCHEDULE_DAY_WIDTH_REM}rem - ${SCHEDULE_BAR_INSET_REM * 2}rem)`,
        top: `${SCHEDULE_BAR_INSET_REM}rem`,
        bottom: `${SCHEDULE_BAR_INSET_REM}rem`,
    } as const;
}

function BookingBar({ booking }: { booking: Booking }) {
    const status = booking.status ?? 'tentative';
    const colorClass =
        STATUS_COLORS[status] ??
        'bg-neutral-100 text-neutral-900 ring-1 ring-neutral-300/40';
    const pos = barPosition(booking.start_idx, booking.end_idx);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={bookingShow(booking.id).url}
                    className={`absolute z-10 flex flex-col justify-center overflow-hidden rounded-md px-2 py-1 text-xs ${colorClass} hover:opacity-80`}
                    style={pos}
                >
                    <div className="truncate font-medium">
                        {booking.name ?? booking.reference}
                    </div>
                    {(booking.client_name || booking.reference) && (
                        <div className="truncate opacity-80">
                            {booking.client_name ?? booking.reference}
                        </div>
                    )}
                </Link>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs">
                <div className="font-medium">
                    {booking.name ?? booking.reference}
                </div>
                {booking.client_name && (
                    <div className="text-[11px] opacity-80">
                        {booking.client_name}
                    </div>
                )}
                {booking.reference && booking.name && (
                    <div className="font-mono text-[11px] opacity-70">
                        {booking.reference}
                    </div>
                )}
                <div className="text-[11px] capitalize opacity-70">
                    Status: {booking.status}
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

function BlackoutBar({ blackout }: { blackout: Blackout }) {
    const pos = barPosition(blackout.start_idx, blackout.end_idx);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <div
                    className="absolute flex items-center justify-center overflow-hidden rounded-md bg-rose-500/15 px-2 py-1 text-xs text-rose-700 ring-1 ring-rose-400/40 dark:text-rose-300"
                    style={pos}
                >
                    <span className="truncate font-medium">
                        ⛔ {blackout.reason}
                    </span>
                </div>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs">
                <div className="font-medium">⛔ {blackout.reason}</div>
                <div className="text-[11px] capitalize opacity-70">
                    Scope: {blackout.scope}-level
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

OpsSchedule.layout = {
    breadcrumbs: [
        { title: 'Operations', href: '/ops/board' },
        { title: 'Schedule', href: '/ops/schedule' },
    ],
};
