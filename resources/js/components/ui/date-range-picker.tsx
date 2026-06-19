import { CalendarIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Value = { startAt: string; endAt: string };

type Props = {
    value: Value;
    onChange: (next: Value) => void;
    className?: string;
    id?: string;
    'aria-describedby'?: string;
    'data-tour-id'?: string;
};

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function toLocalIso(d: Date): string {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function parseLocal(iso: string): Date {
    return new Date(iso);
}

function timeOf(d: Date): string {
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function combineDateAndTime(date: Date, hhmm: string): Date {
    const [h, m] = hhmm.split(':').map(Number);
    const d = new Date(date);
    d.setHours(h ?? 0, m ?? 0, 0, 0);

    return d;
}

function formatRangeLabel(start: Date, end: Date): string {
    const sameDay = start.toDateString() === end.toDateString();
    const dateFmt = (d: Date, opts: Intl.DateTimeFormatOptions) =>
        new Intl.DateTimeFormat(undefined, opts).format(d);
    const time = (d: Date) =>
        dateFmt(d, { hour: 'numeric', minute: '2-digit' });

    if (sameDay) {
        return `${dateFmt(start, { month: 'short', day: 'numeric', year: 'numeric' })} · ${time(start)} - ${time(end)}`;
    }

    return `${dateFmt(start, { month: 'short', day: 'numeric' })} ${time(start)} - ${dateFmt(end, { month: 'short', day: 'numeric', year: 'numeric' })} ${time(end)}`;
}

export function DateRangePicker({ value, onChange, className, id, ...aria }: Props) {
    const [open, setOpen] = useState(false);

    const start = useMemo(() => parseLocal(value.startAt), [value.startAt]);
    const end = useMemo(() => parseLocal(value.endAt), [value.endAt]);

    const range: DateRange = { from: start, to: end };

    function emitRange(next: DateRange | undefined) {
        if (!next?.from) {
            return;
        }

        const newStart = combineDateAndTime(next.from, timeOf(start));
        const newEnd = combineDateAndTime(next.to ?? next.from, timeOf(end));

        // If user only picked the start (no `to` yet), make end the same day.
        // If end < start, snap end to start.
        const safeEnd = newEnd.getTime() < newStart.getTime() ? newStart : newEnd;

        onChange({
            startAt: toLocalIso(newStart),
            endAt: toLocalIso(safeEnd),
        });
    }

    function emitStartTime(hhmm: string) {
        const prevDuration = end.getTime() - start.getTime();
        const newStart = combineDateAndTime(start, hhmm);
        const newEnd = new Date(newStart.getTime() + (prevDuration > 0 ? prevDuration : 2 * 60 * 60 * 1000));

        onChange({
            startAt: toLocalIso(newStart),
            endAt: toLocalIso(newEnd),
        });
    }

    function emitEndTime(hhmm: string) {
        const newEnd = combineDateAndTime(end, hhmm);
        const safeEnd = newEnd.getTime() <= start.getTime()
            ? new Date(start.getTime() + 60 * 60 * 1000)
            : newEnd;

        onChange({ startAt: value.startAt, endAt: toLocalIso(safeEnd) });
    }

    const label = formatRangeLabel(start, end);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    aria-haspopup="dialog"
                    {...aria}
                    className={cn('w-full justify-start text-left font-normal', className)}
                >
                    <CalendarIcon className="mr-2 size-4 opacity-70" />
                    <span className="truncate">{label}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="range"
                    selected={range}
                    onSelect={emitRange}
                    numberOfMonths={2}
                    defaultMonth={start}
                />
                <div className="grid grid-cols-2 gap-3 border-t border-border p-3">
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-start-time`} className="text-xs text-muted-foreground">Starts at</Label>
                        <Input
                            id={`${id}-start-time`}
                            type="time"
                            value={timeOf(start)}
                            onChange={(e) => emitStartTime(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-end-time`} className="text-xs text-muted-foreground">Ends at</Label>
                        <Input
                            id={`${id}-end-time`}
                            type="time"
                            value={timeOf(end)}
                            onChange={(e) => emitEndTime(e.target.value)}
                        />
                    </div>
                </div>
                <div className="flex justify-end border-t border-border p-2">
                    <Button type="button" size="sm" onClick={() => setOpen(false)}>
                        Done
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
