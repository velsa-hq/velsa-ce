import { Head } from '@inertiajs/react';
import type {
    BookingFormInitial,
    ClientOption,
    VenueOption,
} from '@/components/bookings/booking-form';
import { BookingForm } from '@/components/bookings/booking-form';
import { show, update } from '@/routes/bookings';

type BookingFields = {
    id: number;
    reference: string;
    venue_id: number;
    client_id: number;
    name: string;
    kind: string | null;
    status: string;
    start_at: string;
    end_at: string;
    total_dollars: string | null;
    attendance_estimate: number | null;
    notes: string | null;
    cancel_reason: string | null;
    spaces: number[];
};

type Props = {
    booking: BookingFields;
    venues: VenueOption[];
    clients: ClientOption[];
    kinds: { value: string; label: string }[];
    statuses: string[];
};

export default function BookingsEdit({
    booking,
    venues,
    clients,
    kinds,
    statuses,
}: Props) {
    const initial: BookingFormInitial = {
        venue_id: booking.venue_id,
        client_id: booking.client_id,
        name: booking.name,
        kind: booking.kind ?? '',
        status: booking.status,
        start_at: booking.start_at,
        end_at: booking.end_at,
        attendance_estimate: booking.attendance_estimate?.toString() ?? '',
        total_dollars: booking.total_dollars ?? '',
        notes: booking.notes ?? '',
        spaces: booking.spaces,
        cancel_reason: booking.cancel_reason ?? '',
    };

    return (
        <>
            <Head title={`Edit ${booking.reference}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit booking
                    </h1>
                    <p className="font-mono text-sm text-muted-foreground">
                        {booking.reference}
                    </p>
                </header>

                <BookingForm
                    formAction={update.form(booking.id)}
                    initial={initial}
                    venues={venues}
                    clients={clients}
                    kinds={kinds}
                    statuses={statuses}
                    showCancelReason
                    submitLabel="Save changes"
                    cancelHref={show(booking.id).url}
                />
            </div>
        </>
    );
}

BookingsEdit.layout = {
    breadcrumbs: [
        { title: 'Bookings', href: '/bookings' },
        { title: 'Edit', href: '#' },
    ],
};
