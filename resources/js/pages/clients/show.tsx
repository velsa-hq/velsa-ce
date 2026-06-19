import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import RecordDocuments from '@/components/record-documents';
import type { RecordDocument } from '@/components/record-documents';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { wallClock } from '@/lib/datetime';
import { show as bookingShow } from '@/routes/bookings';
import { destroy, edit, index, restore } from '@/routes/clients';
import {
    destroy as destroyContact,
    store as storeContact,
    update as updateContact,
} from '@/routes/clients/contacts';
import { show as contractShow } from '@/routes/contracts';
import { show as leadShow } from '@/routes/leads';

type Contact = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    role: string | null;
    is_primary: boolean;
};

type Client = {
    id: number;
    name: string;
    type: string | null;
    industry: string | null;
    source: string | null;
    notes: string | null;
    retired_at: string | null;
    primary_contact: Contact | null;
    contacts: Contact[];
};

type BookingRow = {
    id: number;
    reference: string;
    name: string;
    status: string;
    start_at: string | null;
    end_at: string | null;
    total_cents: number;
    venue_name: string | null;
};

type LeadRow = {
    id: number;
    name: string;
    stage: string;
    estimated_value_cents: number;
    probability: number;
    expected_close_date: string | null;
    venue_name: string | null;
    closed_at: string | null;
};

type ContractRow = {
    id: number;
    reference: string;
    status: string;
    total_cents: number;
    sent_at: string | null;
    signed_at: string | null;
    booking: { id: number; reference: string; name: string } | null;
};

type Props = {
    client: Client;
    bookings: BookingRow[];
    leads: LeadRow[];
    contracts: ContractRow[];
    documents: RecordDocument[];
};

const TYPE_LABELS: Record<string, string> = {
    individual: 'Individual',
    business: 'Business',
    government: 'Government',
    nonprofit: 'Non-profit',
    educational: 'Educational',
};

const BOOKING_STATUS_COLORS: Record<string, string> = {
    inquiry:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

const LEAD_STAGE_LABELS: Record<string, string> = {
    new: 'New',
    qualified: 'Qualified',
    proposal_sent: 'Proposal sent',
    contract_sent: 'Contract sent',
    won: 'Won',
    lost: 'Lost',
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
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function ClientsShow({
    client,
    bookings,
    leads,
    contracts,
    documents,
}: Props) {
    const totalRevenue = bookings
        .filter((b) => b.status === 'definite' || b.status === 'completed')
        .reduce((sum, b) => sum + b.total_cents, 0);
    const openLeads = leads.filter((l) => l.closed_at === null);
    const wonLeads = leads.filter((l) => l.stage === 'won');

    return (
        <>
            <Head title={`${client.name} · Client`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href={index().url}
                            className="text-xs text-muted-foreground hover:text-foreground"
                        >
                            Clients
                        </Link>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {client.name}
                            </h1>
                            {client.type ? (
                                <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                    {TYPE_LABELS[client.type] ?? client.type}
                                </span>
                            ) : null}
                            {client.retired_at ? (
                                <span className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-900 dark:bg-rose-900/40 dark:text-rose-100">
                                    Retired
                                </span>
                            ) : null}
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {client.industry ?? '-'}
                            {client.source ? (
                                <> · sourced from {client.source}</>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(client.id).url}>Edit</Link>
                        </Button>
                        {client.retired_at ? (
                            <Button
                                type="button"
                                size="sm"
                                onClick={() =>
                                    router.patch(
                                        restore(client.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Restore
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.delete(destroy(client.id).url)
                                }
                                data-tour-id="client-retire"
                            >
                                Retire
                            </Button>
                        )}
                    </div>
                </header>

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="Bookings"
                            value={bookings.length.toLocaleString()}
                            sub={
                                totalRevenue > 0
                                    ? `${money(totalRevenue)} confirmed`
                                    : null
                            }
                        />
                        <Detail
                            label="Leads"
                            value={`${openLeads.length} open · ${wonLeads.length} won`}
                            sub={`${leads.length} total`}
                        />
                        <Detail
                            label="Contracts"
                            value={contracts.length.toLocaleString()}
                        />
                        <Detail
                            label="Primary contact"
                            value={client.primary_contact?.name ?? '-'}
                            sub={client.primary_contact?.email ?? null}
                        />
                    </CardContent>
                </Card>

                <ContactsCard clientId={client.id} contacts={client.contacts} />

                {client.notes ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">Notes</h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {client.notes}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Bookings</h2>
                            <span className="text-xs text-muted-foreground">
                                {bookings.length}
                            </span>
                        </div>
                        {bookings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No bookings yet.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {bookings.map((b) => (
                                    <li
                                        key={b.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <Link
                                            href={bookingShow(b.id).url}
                                            className="min-w-0 flex-1 hover:underline"
                                        >
                                            <div className="font-medium">
                                                {b.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    {b.reference}
                                                </span>
                                                {b.venue_name ? (
                                                    <> · {b.venue_name}</>
                                                ) : null}
                                                {b.start_at ? (
                                                    <>
                                                        {' '}
                                                        ·{' '}
                                                        {formatDate(b.start_at)}
                                                    </>
                                                ) : null}
                                            </div>
                                        </Link>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${BOOKING_STATUS_COLORS[b.status] ?? ''}`}
                                        >
                                            {b.status}
                                        </span>
                                        <span className="font-mono text-xs">
                                            {money(b.total_cents)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Leads</h2>
                            <span className="text-xs text-muted-foreground">
                                {leads.length}
                            </span>
                        </div>
                        {leads.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No leads.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {leads.map((l) => (
                                    <li
                                        key={l.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <Link
                                            href={leadShow(l.id).url}
                                            className="min-w-0 flex-1 hover:underline"
                                        >
                                            <div className="font-medium">
                                                {l.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {LEAD_STAGE_LABELS[l.stage] ??
                                                    l.stage}
                                                {l.venue_name ? (
                                                    <> · {l.venue_name}</>
                                                ) : null}
                                                {l.expected_close_date &&
                                                !l.closed_at ? (
                                                    <>
                                                        {' '}
                                                        · closes{' '}
                                                        {formatDate(
                                                            l.expected_close_date,
                                                        )}
                                                    </>
                                                ) : null}
                                                {l.closed_at ? (
                                                    <> · closed</>
                                                ) : null}
                                            </div>
                                        </Link>
                                        <span className="font-mono text-xs">
                                            {l.estimated_value_cents > 0
                                                ? money(l.estimated_value_cents)
                                                : '-'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Contracts</h2>
                            <span className="text-xs text-muted-foreground">
                                {contracts.length}
                            </span>
                        </div>
                        {contracts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No contracts.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {contracts.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={contractShow(c.id).url}
                                                className="block font-mono text-xs hover:underline"
                                            >
                                                {c.reference}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {c.status.replace('_', ' ')}
                                                {c.booking ? (
                                                    <>
                                                        {' · '}
                                                        <Link
                                                            href={
                                                                bookingShow(
                                                                    c.booking
                                                                        .id,
                                                                ).url
                                                            }
                                                            className="hover:underline"
                                                        >
                                                            {c.booking.name}
                                                        </Link>
                                                    </>
                                                ) : null}
                                            </div>
                                        </div>
                                        <span className="font-mono text-xs">
                                            {money(c.total_cents)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <RecordDocuments
                    documents={documents}
                    storeUrl={`/clients/${client.id}/documents`}
                    destroyUrl={(id) => `/clients/${client.id}/documents/${id}`}
                />

                <div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to clients
                    </Link>
                </div>
            </div>
        </>
    );
}

function ContactsCard({
    clientId,
    contacts,
}: {
    clientId: number;
    contacts: Contact[];
}) {
    const [editing, setEditing] = useState<Contact | null>(null);
    const [adding, setAdding] = useState(false);

    const setPrimary = (c: Contact) =>
        router.put(
            updateContact({ client: clientId, contact: c.id }).url,
            {
                name: c.name,
                role: c.role ?? '',
                email: c.email ?? '',
                phone: c.phone ?? '',
                is_primary: true,
            },
            { preserveScroll: true },
        );

    const remove = (c: Contact) =>
        router.delete(destroyContact({ client: clientId, contact: c.id }).url, {
            preserveScroll: true,
        });

    return (
        <Card>
            <CardContent className="flex flex-col gap-3 p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold">Contacts</h2>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => setAdding(true)}
                        data-tour-id="client-add-contact"
                    >
                        + Add contact
                    </Button>
                </div>
                {contacts.length === 0 ? (
                    <p className="text-xs text-muted-foreground italic">
                        No contacts yet.
                    </p>
                ) : (
                    <ul className="grid gap-2 sm:grid-cols-2">
                        {contacts.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-col gap-1 rounded-md border border-border p-2 text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {c.name}
                                    </span>
                                    {c.is_primary ? (
                                        <span className="text-[10px] tracking-wide text-muted-foreground uppercase">
                                            primary
                                        </span>
                                    ) : null}
                                </div>
                                {c.role ? (
                                    <span className="text-xs text-muted-foreground">
                                        {c.role}
                                    </span>
                                ) : null}
                                {c.email ? (
                                    <span className="text-xs text-muted-foreground">
                                        {c.email}
                                    </span>
                                ) : null}
                                {c.phone ? (
                                    <span className="text-xs text-muted-foreground">
                                        {c.phone}
                                    </span>
                                ) : null}
                                <div className="mt-1 flex flex-wrap gap-3">
                                    {!c.is_primary ? (
                                        <button
                                            type="button"
                                            onClick={() => setPrimary(c)}
                                            className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                        >
                                            Set primary
                                        </button>
                                    ) : null}
                                    <button
                                        type="button"
                                        onClick={() => setEditing(c)}
                                        className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => remove(c)}
                                        className="text-xs text-rose-600 hover:underline dark:text-rose-400"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            {adding ? (
                <ContactForm
                    key="new"
                    clientId={clientId}
                    contact={null}
                    onClose={() => setAdding(false)}
                />
            ) : null}
            {editing ? (
                <ContactForm
                    key={editing.id}
                    clientId={clientId}
                    contact={editing}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </Card>
    );
}

function ContactForm({
    clientId,
    contact,
    onClose,
}: {
    clientId: number;
    contact: Contact | null;
    onClose: () => void;
}) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: contact?.name ?? '',
        role: contact?.role ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        is_primary: contact?.is_primary ?? false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: onClose };

        if (contact) {
            put(
                updateContact({ client: clientId, contact: contact.id }).url,
                opts,
            );
        } else {
            post(storeContact(clientId).url, opts);
        }
    };

    return (
        <Dialog open onOpenChange={(o) => (o ? null : onClose())}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {contact ? 'Edit contact' : 'Add contact'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    <div className="grid gap-1">
                        <Label htmlFor="ct_name">Name</Label>
                        <Input
                            id="ct_name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputErrorText message={errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ct_role">Role / title</Label>
                        <Input
                            id="ct_role"
                            value={data.role}
                            onChange={(e) => setData('role', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ct_email">Email</Label>
                        <Input
                            id="ct_email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputErrorText message={errors.email} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ct_phone">Phone</Label>
                        <Input
                            id="ct_phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_primary}
                            onChange={(e) =>
                                setData('is_primary', e.target.checked)
                            }
                        />
                        Primary contact
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {contact ? 'Save' : 'Add'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InputErrorText({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <span className="text-xs text-rose-600 dark:text-rose-400">
            {message}
        </span>
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

ClientsShow.layout = {
    breadcrumbs: [{ title: 'Clients', href: '/clients' }],
};
