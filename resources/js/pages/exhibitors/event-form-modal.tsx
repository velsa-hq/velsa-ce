import { router } from '@inertiajs/react';
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

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

export type BookingOption = {
    id: number;
    reference: string;
    name: string;
    kind: string | null;
};

export type EventValues = {
    id: number;
    name: string;
    booking_id: number | null;
    portal_slug: string | null;
    default_booth_size: string | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    advance_rate_deadline: string | null;
    late_order_surcharge_pct: number | null;
};

/** Trim an ISO timestamp down to the YYYY-MM-DD a date input expects. */
function toDateInput(iso: string | null): string {
    return iso ? iso.slice(0, 10) : '';
}

export default function EventFormModal({
    open,
    onClose,
    bookings,
    mode,
    event,
    defaultBookingId,
}: {
    open: boolean;
    onClose: () => void;
    bookings: BookingOption[];
    mode: 'create' | 'edit';
    event?: EventValues;
    defaultBookingId?: number | null;
}) {
    const [form, setForm] = useState({
        name: event?.name ?? '',
        booking_id: String(
            event?.booking_id ?? defaultBookingId ?? bookings[0]?.id ?? '',
        ),
        portal_slug: event?.portal_slug ?? '',
        default_booth_size: event?.default_booth_size ?? '10x10',
        registration_opens_at: toDateInput(
            event?.registration_opens_at ?? null,
        ),
        registration_closes_at: toDateInput(
            event?.registration_closes_at ?? null,
        ),
        advance_rate_deadline: toDateInput(
            event?.advance_rate_deadline ?? null,
        ),
        late_order_surcharge_pct: String(event?.late_order_surcharge_pct ?? ''),
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
            name: form.name,
            booking_id: Number(form.booking_id),
            portal_slug: form.portal_slug || null,
            default_booth_size: form.default_booth_size || null,
            registration_opens_at: form.registration_opens_at || null,
            registration_closes_at: form.registration_closes_at || null,
            advance_rate_deadline: form.advance_rate_deadline || null,
            late_order_surcharge_pct: form.late_order_surcharge_pct
                ? Number(form.late_order_surcharge_pct)
                : null,
        };

        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSaving(false),
        };

        if (mode === 'edit' && event) {
            router.patch(`/exhibitor-events/${event.id}`, payload, opts);
        } else {
            router.post('/exhibitor-events', payload, opts);
        }
    };

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
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {mode === 'edit'
                                    ? 'Edit exhibitor event'
                                    : 'New exhibitor event'}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="ev-name">Event name</Label>
                                <Input
                                    id="ev-name"
                                    value={form.name}
                                    onChange={(e) =>
                                        set('name', e.target.value)
                                    }
                                    placeholder="e.g. Spring Home & Garden Expo"
                                    required
                                    data-tour-id="ev-name"
                                />
                            </div>
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="ev-booking">Booking</Label>
                                <select
                                    id="ev-booking"
                                    value={form.booking_id}
                                    onChange={(e) =>
                                        set('booking_id', e.target.value)
                                    }
                                    className={selectClass}
                                    required
                                >
                                    {bookings.map((b) => (
                                        <option key={b.id} value={b.id}>
                                            {b.reference} - {b.name}
                                            {b.kind ? ` (${b.kind})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-slug">
                                    Portal slug (optional)
                                </Label>
                                <Input
                                    id="ev-slug"
                                    value={form.portal_slug}
                                    onChange={(e) =>
                                        set('portal_slug', e.target.value)
                                    }
                                    placeholder="auto from name"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-size">
                                    Default booth size
                                </Label>
                                <Input
                                    id="ev-size"
                                    value={form.default_booth_size}
                                    onChange={(e) =>
                                        set(
                                            'default_booth_size',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 10x10"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-opens">
                                    Registration opens
                                </Label>
                                <Input
                                    id="ev-opens"
                                    type="date"
                                    value={form.registration_opens_at}
                                    onChange={(e) =>
                                        set(
                                            'registration_opens_at',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-closes">
                                    Registration closes
                                </Label>
                                <Input
                                    id="ev-closes"
                                    type="date"
                                    value={form.registration_closes_at}
                                    onChange={(e) =>
                                        set(
                                            'registration_closes_at',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-advance">
                                    Advance-rate deadline
                                </Label>
                                <Input
                                    id="ev-advance"
                                    type="date"
                                    value={form.advance_rate_deadline}
                                    onChange={(e) =>
                                        set(
                                            'advance_rate_deadline',
                                            e.target.value,
                                        )
                                    }
                                    data-tour-id="ev-advance-deadline"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="ev-surcharge">
                                    Late-order surcharge (%)
                                </Label>
                                <Input
                                    id="ev-surcharge"
                                    type="number"
                                    min="0"
                                    max="100"
                                    value={form.late_order_surcharge_pct}
                                    onChange={(e) =>
                                        set(
                                            'late_order_surcharge_pct',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 15"
                                    data-tour-id="ev-surcharge-pct"
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {mode === 'edit'
                                    ? 'Save changes'
                                    : 'Create event'}
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
