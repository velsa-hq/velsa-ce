import { Head } from '@inertiajs/react';
import type {
    BookingFormInitial,
    ClientOption,
    VenueOption,
} from '@/components/bookings/booking-form';
import { BookingForm } from '@/components/bookings/booking-form';
import { index, store } from '@/routes/bookings';

type FromLead = {
    id: number;
    name: string;
    client_id: number | null;
    venue_id: number | null;
    estimated_value_cents: number;
    expected_close_date: string | null;
} | null;

type Prefill = { venue_id: number; space_id: number | null } | null;

type Props = {
    venues: VenueOption[];
    clients: ClientOption[];
    client_types: string[];
    kinds: { value: string; label: string }[];
    creatable_statuses: string[];
    from_lead: FromLead;
    prefill: Prefill;
};

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function toLocalIso(d: Date): string {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function startFromExpectedClose(iso: string): string {
    const d = new Date(`${iso}T10:00`);

    return toLocalIso(d);
}

function endFromExpectedClose(iso: string): string {
    const d = new Date(`${iso}T16:00`);

    return toLocalIso(d);
}

function defaultStart(): string {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    d.setHours(10, 0, 0, 0);

    return toLocalIso(d);
}

function defaultEnd(): string {
    const d = new Date();
    d.setDate(d.getDate() + 14);
    d.setHours(16, 0, 0, 0);

    return toLocalIso(d);
}

export default function BookingsCreate({
    venues,
    clients,
    client_types,
    kinds,
    creatable_statuses,
    from_lead,
    prefill,
}: Props) {
    const initial: BookingFormInitial = from_lead
        ? {
              venue_id: from_lead.venue_id ?? '',
              client_id: from_lead.client_id ?? '',
              name: from_lead.name,
              kind: '',
              status: 'tentative',
              start_at: from_lead.expected_close_date
                  ? startFromExpectedClose(from_lead.expected_close_date)
                  : defaultStart(),
              end_at: from_lead.expected_close_date
                  ? endFromExpectedClose(from_lead.expected_close_date)
                  : defaultEnd(),
              attendance_estimate: '',
              total_dollars:
                  from_lead.estimated_value_cents > 0
                      ? (from_lead.estimated_value_cents / 100).toFixed(2)
                      : '',
              notes: '',
              spaces: [],
              cancel_reason: '',
          }
        : {
              venue_id: prefill?.venue_id ?? '',
              client_id: '',
              name: '',
              kind: '',
              status: 'inquiry',
              start_at: defaultStart(),
              end_at: defaultEnd(),
              attendance_estimate: '',
              total_dollars: '',
              notes: '',
              spaces: prefill?.space_id ? [prefill.space_id] : [],
              cancel_reason: '',
          };

    return (
        <>
            <Head title="New booking" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {from_lead ? 'Convert lead to booking' : 'New booking'}
                    </h1>
                    {from_lead ? (
                        <p className="text-sm text-muted-foreground">
                            Pre-filled from lead{' '}
                            <span className="font-medium">
                                {from_lead.name}
                            </span>
                            . Adjust the dates and spaces, then save - the lead
                            will be marked converted.
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Create a booking and lock in one or more spaces. The
                            booking reference is assigned automatically.
                        </p>
                    )}
                </header>

                <BookingForm
                    formAction={store.form()}
                    initial={initial}
                    venues={venues}
                    clients={clients}
                    clientTypes={client_types}
                    kinds={kinds}
                    statuses={creatable_statuses}
                    allowNewClient={from_lead === null}
                    leadId={from_lead?.id ?? null}
                    submitLabel={
                        from_lead ? 'Convert to booking' : 'Create booking'
                    }
                    cancelHref={index().url}
                />
            </div>
        </>
    );
}

BookingsCreate.layout = {
    breadcrumbs: [
        { title: 'Bookings', href: '/bookings' },
        { title: 'New', href: '/bookings/create' },
    ],
};
