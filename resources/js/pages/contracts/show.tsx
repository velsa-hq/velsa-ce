import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { show as bookingShow } from '@/routes/bookings';
import { show as clientShow } from '@/routes/clients';
import {
    destroy as destroyContract,
    index,
    send,
    show,
    signedPdf,
    voidMethod as voidContract,
} from '@/routes/contracts';

type Signer = {
    id: number;
    signing_order: number;
    role: string | null;
    name: string;
    email: string;
    viewed_at: string | null;
    signed_at: string | null;
    declined_at: string | null;
};

type RelatedContract = {
    id: number;
    reference: string;
    kind: string;
    status?: string;
    created_at?: string | null;
};

type Contract = {
    id: number;
    reference: string;
    kind: string;
    status: string;
    total_cents: number;
    rendered_html: string | null;
    has_signed_pdf: boolean;
    provider: string | null;
    provider_envelope_id: string | null;
    sent_at: string | null;
    viewed_at: string | null;
    signed_at: string | null;
    declined_at: string | null;
    expired_at: string | null;
    voided_at: string | null;
    decline_reason: string | null;
    created_at: string | null;
    booking: {
        id: number;
        reference: string;
        name: string;
        status: string | null;
        start_at: string | null;
        end_at: string | null;
        client: { id: number; name: string } | null;
        venue: { id: number; name: string; slug: string } | null;
    } | null;
    signers: Signer[];
    creator: { id: number; name: string; email: string } | null;
    parent: { id: number; reference: string; kind: string } | null;
    addenda: RelatedContract[];
};

type Props = { contract: Contract };

const STATUS_COLORS: Record<string, string> = {
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    sent: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    viewed: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    partially_signed:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    signed: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    declined:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    expired:
        'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    voided: 'bg-neutral-300 text-neutral-700 dark:bg-neutral-600 dark:text-neutral-200',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
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

export default function ContractsShow({ contract }: Props) {
    const [sendOpen, setSendOpen] = useState(false);

    const lifecycle: Array<{
        label: string;
        at: string | null;
        tone?: string;
    }> = [
        { label: 'Created', at: contract.created_at },
        { label: 'Sent', at: contract.sent_at },
        { label: 'First viewed', at: contract.viewed_at },
        { label: 'Signed', at: contract.signed_at, tone: 'good' },
        { label: 'Declined', at: contract.declined_at, tone: 'bad' },
        { label: 'Expired', at: contract.expired_at, tone: 'bad' },
        { label: 'Voided', at: contract.voided_at, tone: 'bad' },
    ].filter((row) => row.at !== null || row.label === 'Created');

    return (
        <>
            <Head title={`${contract.reference} · Contract`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1
                                className="font-mono text-2xl font-semibold tracking-tight"
                                data-tour-id="ct-reference"
                            >
                                {contract.reference}
                            </h1>
                            <span
                                data-tour-id="ct-status-pill"
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[contract.status] ?? ''}`}
                            >
                                {contract.status.replace('_', ' ')}
                            </span>
                            <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                {contract.kind}
                            </span>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            {contract.booking ? (
                                <>
                                    For{' '}
                                    <Link
                                        href={
                                            bookingShow(contract.booking.id).url
                                        }
                                        className="font-medium hover:underline"
                                    >
                                        {contract.booking.name}
                                    </Link>
                                    {contract.booking.client ? (
                                        <>
                                            {' · '}
                                            <Link
                                                href={
                                                    clientShow(
                                                        contract.booking.client
                                                            .id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                            >
                                                {contract.booking.client.name}
                                            </Link>
                                        </>
                                    ) : null}
                                    {contract.booking.venue ? (
                                        <>
                                            {' · '}
                                            <Link
                                                href={`/venues/${contract.booking.venue.slug}`}
                                                className="hover:underline"
                                            >
                                                {contract.booking.venue.name}
                                            </Link>
                                        </>
                                    ) : null}
                                </>
                            ) : (
                                <span className="italic">No booking</span>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-sm">
                            {money(contract.total_cents)}
                        </span>
                        {contract.has_signed_pdf ? (
                            <Button asChild variant="outline">
                                <a
                                    href={signedPdf(contract.id).url}
                                    data-tour-id="ct-download-signed"
                                >
                                    Download signed PDF
                                </a>
                            </Button>
                        ) : null}
                        <Button asChild variant="outline">
                            <a
                                href={`/contracts/${contract.id}/document.doc`}
                                data-tour-id="ct-download-word"
                            >
                                Download as Word
                            </a>
                        </Button>
                        {contract.status === 'draft' ? (
                            <Button
                                type="button"
                                onClick={() => setSendOpen(true)}
                                data-tour-id="ct-send"
                            >
                                Send via DocuSign
                            </Button>
                        ) : null}
                        {contract.kind !== 'addendum' &&
                        (contract.status === 'signed' ||
                            contract.status === 'partially_signed') ? (
                            <CreateAddendumButton contract={contract} />
                        ) : null}
                        {['sent', 'viewed', 'partially_signed'].includes(
                            contract.status,
                        ) ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    if (
                                        confirm(
                                            `Void contract ${contract.reference}? This cancels the signature request.`,
                                        )
                                    ) {
                                        router.post(
                                            voidContract(contract.id).url,
                                        );
                                    }
                                }}
                                data-tour-id="ct-void"
                            >
                                Void
                            </Button>
                        ) : null}
                        {['draft', 'declined', 'expired', 'voided'].includes(
                            contract.status,
                        ) ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    if (
                                        confirm(
                                            `Delete contract ${contract.reference}? It moves to the archive and can be restored.`,
                                        )
                                    ) {
                                        router.delete(
                                            destroyContract(contract.id).url,
                                        );
                                    }
                                }}
                                data-tour-id="ct-delete"
                            >
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </header>

                {contract.status === 'declined' && contract.decline_reason ? (
                    <Card className="border-rose-300 bg-rose-50/50 dark:border-rose-900 dark:bg-rose-950/30">
                        <CardContent className="p-4 text-sm">
                            <div className="font-medium text-rose-900 dark:text-rose-200">
                                Declined
                            </div>
                            <div className="mt-1 text-rose-800 dark:text-rose-300">
                                {contract.decline_reason}
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card>
                            <CardContent className="flex flex-col gap-3 p-4">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-sm font-semibold">
                                        Signers
                                    </h2>
                                    <span className="text-xs text-muted-foreground">
                                        {contract.signers.length}
                                    </span>
                                </div>
                                {contract.signers.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No signers attached yet. Send the
                                        contract to add the first one.
                                    </p>
                                ) : (
                                    <ol className="flex flex-col gap-2">
                                        {contract.signers.map((s) => (
                                            <li
                                                key={s.id}
                                                className="flex items-start justify-between gap-3 rounded-md border border-border p-2 text-sm"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-[10px] tracking-wide text-muted-foreground uppercase">
                                                            #{s.signing_order}
                                                        </span>
                                                        <span className="font-medium">
                                                            {s.name}
                                                        </span>
                                                        {s.role ? (
                                                            <span className="text-xs text-muted-foreground">
                                                                {s.role}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {s.email}
                                                    </div>
                                                </div>
                                                <div className="flex shrink-0 flex-col items-end text-xs">
                                                    {s.signed_at ? (
                                                        <span className="font-medium text-emerald-700 dark:text-emerald-300">
                                                            ✓ signed{' '}
                                                            {formatDateTime(
                                                                s.signed_at,
                                                            )}
                                                        </span>
                                                    ) : s.declined_at ? (
                                                        <span className="font-medium text-rose-700 dark:text-rose-300">
                                                            ✗ declined{' '}
                                                            {formatDateTime(
                                                                s.declined_at,
                                                            )}
                                                        </span>
                                                    ) : s.viewed_at ? (
                                                        <span className="text-sky-700 dark:text-sky-300">
                                                            👁 viewed{' '}
                                                            {formatDateTime(
                                                                s.viewed_at,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            awaiting
                                                        </span>
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex flex-col gap-3 p-4">
                                <h2 className="text-sm font-semibold">
                                    Rendered contract
                                </h2>
                                {contract.rendered_html ? (
                                    <div
                                        className="max-h-96 overflow-auto rounded-md border border-border bg-card p-4 text-sm [&_h1]:mb-3 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:mt-4 [&_h2]:mb-2 [&_h2]:text-base [&_h2]:font-semibold [&_li]:my-1 [&_p]:mb-2 [&_p]:leading-relaxed [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-6"
                                        dangerouslySetInnerHTML={
                                            /* nosemgrep: react-dangerouslysetinnerhtml -- server-escaped by DocumentTemplate::render (APSC-DV-002490) */ {
                                                __html: contract.rendered_html,
                                            }
                                        }
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground italic">
                                        No template was attached when this
                                        contract was drafted, so there's no
                                        rendered body to preview.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardContent className="flex flex-col gap-3 p-4">
                                <h2 className="text-sm font-semibold">
                                    Lifecycle
                                </h2>
                                <ol className="flex flex-col gap-3 text-sm">
                                    {lifecycle.map((row) => (
                                        <li
                                            key={row.label}
                                            className="flex items-start gap-2"
                                        >
                                            <span
                                                className={`mt-1 size-2 shrink-0 rounded-full ${
                                                    row.at === null
                                                        ? 'bg-muted'
                                                        : row.tone === 'good'
                                                          ? 'bg-emerald-500'
                                                          : row.tone === 'bad'
                                                            ? 'bg-rose-500'
                                                            : 'bg-primary'
                                                }`}
                                            />
                                            <div>
                                                <div
                                                    className={
                                                        row.at === null
                                                            ? 'text-muted-foreground'
                                                            : 'font-medium'
                                                    }
                                                >
                                                    {row.label}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.at
                                                        ? formatDateTime(row.at)
                                                        : 'not yet'}
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="flex flex-col gap-2 p-4 text-sm">
                                <h2 className="text-sm font-semibold">
                                    Provider
                                </h2>
                                <div className="flex flex-col gap-1 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Provider
                                        </span>
                                        <span>{contract.provider ?? '-'}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Envelope
                                        </span>
                                        <span className="font-mono">
                                            {contract.provider_envelope_id ??
                                                '-'}
                                        </span>
                                    </div>
                                    {contract.creator ? (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Created by
                                            </span>
                                            <span>{contract.creator.name}</span>
                                        </div>
                                    ) : null}
                                </div>
                            </CardContent>
                        </Card>

                        {contract.parent || contract.addenda.length > 0 ? (
                            <Card>
                                <CardContent className="flex flex-col gap-3 p-4 text-sm">
                                    <h2 className="text-sm font-semibold">
                                        Related
                                    </h2>
                                    {contract.parent ? (
                                        <div className="text-xs">
                                            <div className="text-muted-foreground">
                                                Parent
                                            </div>
                                            <Link
                                                href={
                                                    show(contract.parent.id).url
                                                }
                                                className="font-mono hover:underline"
                                            >
                                                {contract.parent.reference}
                                            </Link>{' '}
                                            <span className="text-muted-foreground">
                                                ({contract.parent.kind})
                                            </span>
                                        </div>
                                    ) : null}
                                    {contract.addenda.length > 0 ? (
                                        <div className="text-xs">
                                            <div className="text-muted-foreground">
                                                Addenda
                                            </div>
                                            <ul className="flex flex-col gap-0.5">
                                                {contract.addenda.map((a) => (
                                                    <li key={a.id}>
                                                        <Link
                                                            href={
                                                                show(a.id).url
                                                            }
                                                            className="font-mono hover:underline"
                                                        >
                                                            {a.reference}
                                                        </Link>{' '}
                                                        <span className="text-muted-foreground">
                                                            ({a.status})
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>

                <div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to contracts
                    </Link>
                </div>
            </div>

            {sendOpen ? (
                <SendForSignatureDialog
                    contract={contract}
                    onClose={() => setSendOpen(false)}
                />
            ) : null}
        </>
    );
}

type SignerDraft = { name: string; email: string; role: string };

function SendForSignatureDialog({
    contract,
    onClose,
}: {
    contract: Contract;
    onClose: () => void;
}) {
    const { data, setData, processing, errors } = useForm<{
        signers: SignerDraft[];
    }>({
        signers: [
            {
                name: contract.booking?.client?.name ?? '',
                email: '',
                role: 'client',
            },
        ],
    });

    const setSigner = (i: number, patch: Partial<SignerDraft>) =>
        setData(
            'signers',
            data.signers.map((s, idx) => (idx === i ? { ...s, ...patch } : s)),
        );
    const addSigner = () =>
        setData('signers', [
            ...data.signers,
            { name: '', email: '', role: 'countersigner' },
        ]);
    const removeSigner = (i: number) =>
        setData(
            'signers',
            data.signers.filter((_, idx) => idx !== i),
        );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // signing_order follows row order (1-based).
        const payload = data.signers.map((s, idx) => ({
            ...s,
            signing_order: idx + 1,
        }));
        router.post(
            send(contract.id).url,
            { signers: payload },
            { preserveScroll: true, onSuccess: onClose },
        );
    };

    return (
        <Dialog open onOpenChange={(o) => (o ? null : onClose())}>
            <DialogContent data-tour-id="ct-send-dialog">
                <DialogHeader>
                    <DialogTitle>Send for signature</DialogTitle>
                    <DialogDescription>
                        Add every signer in the order they should sign. DocuSign
                        routes to them sequentially by that order.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    {data.signers.map((s, i) => (
                        <div
                            key={i}
                            className="flex flex-col gap-2 rounded-md border border-border p-2"
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Signer {i + 1}
                                </span>
                                {data.signers.length > 1 ? (
                                    <button
                                        type="button"
                                        onClick={() => removeSigner(i)}
                                        className="text-xs text-rose-600 hover:underline dark:text-rose-400"
                                    >
                                        Remove
                                    </button>
                                ) : null}
                            </div>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-1">
                                    <Label htmlFor={`s_name_${i}`}>Name</Label>
                                    <Input
                                        id={`s_name_${i}`}
                                        value={s.name}
                                        onChange={(e) =>
                                            setSigner(i, {
                                                name: e.target.value,
                                            })
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor={`s_email_${i}`}>
                                        Email
                                    </Label>
                                    <Input
                                        id={`s_email_${i}`}
                                        type="email"
                                        value={s.email}
                                        onChange={(e) =>
                                            setSigner(i, {
                                                email: e.target.value,
                                            })
                                        }
                                        required
                                        data-tour-id={
                                            i === 0
                                                ? 'ct-send-signer-email'
                                                : undefined
                                        }
                                    />
                                </div>
                                <div className="grid gap-1 sm:col-span-2">
                                    <Label htmlFor={`s_role_${i}`}>Role</Label>
                                    <Input
                                        id={`s_role_${i}`}
                                        value={s.role}
                                        onChange={(e) =>
                                            setSigner(i, {
                                                role: e.target.value,
                                            })
                                        }
                                        placeholder="client, venue, witness, ..."
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                    {errors.signers ? (
                        <span className="text-xs text-rose-600 dark:text-rose-400">
                            {errors.signers}
                        </span>
                    ) : null}
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={addSigner}
                            data-tour-id="ct-send-add-signer"
                        >
                            + Add signer
                        </Button>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            data-tour-id="ct-send-submit"
                        >
                            {processing && <Spinner />}
                            Send
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CreateAddendumButton({ contract }: { contract: Contract }) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const onSubmit = () => {
        router.post(
            `/contracts/${contract.id}/addenda`,
            { reason: reason || undefined },
            {
                preserveScroll: true,
                onStart: () => setSubmitting(true),
                onFinish: () => setSubmitting(false),
                onSuccess: () => {
                    setOpen(false);
                    setReason('');
                },
            },
        );
    };

    if (!open) {
        return (
            <Button
                type="button"
                variant="outline"
                onClick={() => setOpen(true)}
                data-tour-id="ct-create-addendum"
                title="Signed contracts are immutable - any change must be a separate Addendum."
            >
                Create addendum
            </Button>
        );
    }

    return (
        <div className="flex w-full flex-col gap-2 rounded-md border border-border bg-muted/20 p-3 md:max-w-md">
            <div className="text-xs font-medium">
                New addendum on {contract.reference}
            </div>
            <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                data-tour-id="ct-addendum-reason"
                placeholder="Reason (e.g. 'Date shift to October 15')"
                rows={2}
                className="w-full rounded-md border border-border bg-background px-2 py-1.5 text-sm"
            />
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setOpen(false);
                        setReason('');
                    }}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    size="sm"
                    onClick={onSubmit}
                    disabled={submitting}
                    data-tour-id="ct-addendum-submit"
                >
                    {submitting && <Spinner />}
                    Draft addendum
                </Button>
            </div>
        </div>
    );
}

ContractsShow.layout = {
    breadcrumbs: [{ title: 'Contracts', href: '/contracts' }],
};
