import type { EventInput } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { OpsViewSwitcher } from '@/components/ops/ops-view-switcher';
import { Card, CardContent } from '@/components/ui/card';

// legend doubles as a status filter; keys match booking status values plus 'blackout'
const LEGEND: {
    key: string;
    label: string;
    bg: string;
    isBlackout?: boolean;
}[] = [
    { key: 'hold', label: 'Hold', bg: '#f59e0b' },
    { key: 'tentative', label: 'Tentative', bg: '#0ea5e9' },
    { key: 'definite', label: 'Definite', bg: '#10b981' },
    { key: 'completed', label: 'Completed', bg: '#8b5cf6' },
    {
        key: 'blackout',
        label: 'Blackout',
        bg: 'rgba(244, 63, 94, 0.18)',
        isBlackout: true,
    },
];

type Event = {
    id: string;
    title: string;
    start: string;
    end?: string | null;
    url?: string;
    display?: 'background' | 'auto';
    backgroundColor?: string;
    borderColor?: string;
    classNames?: string[];
    extendedProps?: Record<string, unknown>;
};

type Venue = { id: number; name: string; slug: string };

type Props = {
    events: Event[];
    venues: Venue[];
    venue_id: number | null;
    window: { start: string; end: string };
};

export default function OpsCalendar({
    events,
    venues,
    venue_id,
    window,
}: Props) {
    const onVenueChange = (value: string) => {
        router.visit('/ops/calendar', {
            method: 'get',
            data: { venue_id: value || undefined },
            preserveScroll: true,
        });
    };

    // legend-driven status filter; empty set shows everything
    const [activeStatuses, setActiveStatuses] = useState<Set<string>>(
        new Set(),
    );

    const toggleStatus = (key: string) =>
        setActiveStatuses((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });

    const clearStatuses = () => setActiveStatuses(new Set());

    const visibleEvents =
        activeStatuses.size === 0
            ? events
            : events.filter((e) => {
                  const key =
                      e.extendedProps?.kind === 'blackout'
                          ? 'blackout'
                          : ((e.extendedProps?.status as string | undefined) ??
                            '');

                  return activeStatuses.has(key);
              });

    // EventInput wants undefined (not null) for open-ended events; server sends null
    const calendarEvents: EventInput[] = visibleEvents.map((e) => ({
        ...e,
        end: e.end ?? undefined,
    }));

    const blackoutCount = events.filter(
        (e) => e.extendedProps?.kind === 'blackout',
    ).length;
    const bookingCount = events.filter(
        (e) => e.extendedProps?.kind === 'booking',
    ).length;

    return (
        <>
            <Head title="Calendar · Operations" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-2">
                    <OpsViewSwitcher current="calendar" />
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <CalendarDays
                            className="size-6 text-primary"
                            aria-hidden
                        />
                        Calendar
                    </h1>
                </header>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 text-sm">
                                Venue
                                <select
                                    value={venue_id ?? ''}
                                    onChange={(e) =>
                                        onVenueChange(e.target.value)
                                    }
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
                            </label>
                            <div className="ml-auto flex items-center gap-3 text-xs text-muted-foreground">
                                <span>{bookingCount} bookings</span>
                                <span>·</span>
                                <span>{blackoutCount} blackouts</span>
                                <span>·</span>
                                <span>
                                    {window.start} - {window.end}
                                </span>
                            </div>
                        </div>
                        <CalendarLegend
                            active={activeStatuses}
                            onToggle={toggleStatus}
                            onClear={clearStatuses}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-3">
                        <FullCalendar
                            plugins={[
                                dayGridPlugin,
                                timeGridPlugin,
                                listPlugin,
                                interactionPlugin,
                            ]}
                            initialView="dayGridMonth"
                            headerToolbar={{
                                left: 'prev,next today',
                                center: 'title',
                                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                            }}
                            buttonText={{
                                today: 'Today',
                                month: 'Month',
                                week: 'Week',
                                day: 'Day',
                                list: 'List',
                            }}
                            events={calendarEvents}
                            height="auto"
                            eventTimeFormat={{
                                hour: 'numeric',
                                minute: '2-digit',
                                meridiem: 'short',
                            }}
                            slotMinTime="06:00:00"
                            slotMaxTime="23:00:00"
                            nowIndicator
                            weekNumbers={false}
                            displayEventTime
                            firstDay={0}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function CalendarLegend({
    active,
    onToggle,
    onClear,
}: {
    active: Set<string>;
    onToggle: (key: string) => void;
    onClear: () => void;
}) {
    const filtering = active.size > 0;

    return (
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <span className="font-medium">
                {filtering ? 'Showing:' : 'Filter by status:'}
            </span>
            {LEGEND.map((item) => {
                const isActive = active.has(item.key);

                return (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => onToggle(item.key)}
                        aria-pressed={isActive}
                        title={`Filter to ${item.label}`}
                        data-tour-id={`ops-cal-status-${item.key}`}
                        className={`inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2 py-0.5 transition ${
                            isActive
                                ? 'border-border bg-muted font-medium text-foreground'
                                : 'border-dashed border-border/60 hover:border-solid hover:bg-muted/60'
                        } ${filtering && !isActive ? 'opacity-40' : ''}`}
                    >
                        <span
                            className={`inline-block size-3 rounded-sm ${
                                item.isBlackout ? 'ring-1 ring-rose-400/60' : ''
                            }`}
                            style={{ backgroundColor: item.bg }}
                            aria-hidden
                        />
                        {item.label}
                    </button>
                );
            })}
            {filtering && (
                <button
                    type="button"
                    onClick={onClear}
                    className="ml-1 rounded-full px-2 py-0.5 underline hover:text-foreground"
                >
                    Clear
                </button>
            )}
        </div>
    );
}

OpsCalendar.layout = {
    breadcrumbs: [
        { title: 'Operations', href: '/ops/board' },
        { title: 'Calendar', href: '/ops/calendar' },
    ],
};
