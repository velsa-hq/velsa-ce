import { Head, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cancel, store } from '@/routes/portal/permits';

type PermitType = { value: string; label: string };

type Permit = {
    id: number;
    permit_type: string;
    details: string;
    status: string;
    status_label: string;
    review_notes: string | null;
    document_url: string | null;
    created_at: string | null;
};

type Props = {
    permits: Permit[];
    permit_types: PermitType[];
};

const selectClass =
    'w-full rounded-md border border-border bg-background px-3 py-2 text-sm';

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    approved: 'default',
    denied: 'destructive',
    cancelled: 'secondary',
};

export default function PortalPermits({ permits, permit_types }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        permit_type: string;
        details: string;
        document: File | null;
    }>({
        permit_type: permit_types[0]?.value ?? 'other',
        details: '',
        document: null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('details', 'document'),
        });
    };

    const withdraw = (id: number) => {
        if (window.confirm('Withdraw this permit request?')) {
            router.post(cancel(id).url, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Permits" />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Activity permits
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Request the venue's sign-off for regulated booth activities
                    - food sampling, open flame, vehicle move-in, and the like.
                    The venue reviews each request; track the outcome here.
                </p>

                {permits.length > 0 && (
                    <ul className="mt-6 space-y-3">
                        {permits.map((p) => (
                            <li
                                key={p.id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[p.status] ??
                                                    'outline'
                                                }
                                            >
                                                {p.status_label}
                                            </Badge>
                                            <span className="font-medium">
                                                {p.permit_type}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm whitespace-pre-line">
                                            {p.details}
                                        </p>
                                        {p.review_notes && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Reviewer note: {p.review_notes}
                                            </p>
                                        )}
                                        {p.document_url && (
                                            <a
                                                href={p.document_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-1 inline-block text-xs text-primary underline"
                                            >
                                                View document
                                            </a>
                                        )}
                                    </div>
                                    {p.status === 'pending' && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            data-tour-id="permit-cancel"
                                            onClick={() => withdraw(p.id)}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={submit}
                    className="mt-8 space-y-4 rounded-lg border border-border p-4"
                    data-tour-id="permit-form"
                >
                    <h2 className="font-semibold">Request a permit</h2>

                    <div className="grid gap-2">
                        <Label htmlFor="permit_type">Permit type</Label>
                        <select
                            id="permit_type"
                            className={selectClass}
                            data-tour-id="permit-type"
                            value={data.permit_type}
                            onChange={(e) =>
                                setData('permit_type', e.target.value)
                            }
                        >
                            {permit_types.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.permit_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="details">Details</Label>
                        <textarea
                            id="details"
                            rows={4}
                            data-tour-id="permit-details"
                            placeholder="What's planned - quantities, times, equipment, anything the venue needs to weigh."
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                            value={data.details}
                            onChange={(e) => setData('details', e.target.value)}
                        />
                        <InputError message={errors.details} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="document">
                            Supporting document (optional, PDF or image)
                        </Label>
                        <input
                            id="document"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            className="text-sm"
                            onChange={(e) =>
                                setData('document', e.target.files?.[0] ?? null)
                            }
                        />
                        <InputError message={errors.document} />
                    </div>

                    <Button
                        type="submit"
                        data-tour-id="permit-submit"
                        disabled={processing}
                    >
                        {processing && <Spinner className="mr-2" />}
                        Submit request
                    </Button>
                </form>
            </div>
        </>
    );
}
