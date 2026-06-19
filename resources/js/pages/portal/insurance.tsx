import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/portal/insurance';

type PolicyType = { value: string; label: string };

type Certificate = {
    id: number;
    policy_type: string;
    carrier: string | null;
    expires_on: string;
    status: string;
    status_label: string;
    review_notes: string | null;
    document_url: string | null;
    created_at: string | null;
};

type Props = {
    certificates: Certificate[];
    policy_types: PolicyType[];
};

const selectClass =
    'w-full rounded-md border border-border bg-background px-3 py-2 text-sm';

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    approved: 'default',
    rejected: 'destructive',
    expired: 'destructive',
};

export default function PortalInsurance({ certificates, policy_types }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        policy_type: string;
        carrier: string;
        policy_number: string;
        expires_on: string;
        document: File | null;
    }>({
        policy_type: policy_types[0]?.value ?? 'general_liability',
        carrier: '',
        policy_number: '',
        expires_on: '',
        document: null,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                reset(
                    'policy_type',
                    'carrier',
                    'policy_number',
                    'expires_on',
                    'document',
                ),
        });
    };

    return (
        <>
            <Head title="Insurance" />
            <div className="mx-auto w-full max-w-3xl p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Insurance certificates
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Upload your Certificate of Insurance for this event. Our
                    team will review it; you can check the status here anytime.
                </p>

                {certificates.length > 0 && (
                    <ul className="mt-6 space-y-3">
                        {certificates.map((c) => (
                            <li
                                key={c.id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            STATUS_VARIANT[c.status] ??
                                            'outline'
                                        }
                                    >
                                        {c.status_label}
                                    </Badge>
                                    <span className="font-medium">
                                        {c.policy_type}
                                    </span>
                                    {c.carrier && (
                                        <span className="text-sm text-muted-foreground">
                                            {c.carrier}
                                        </span>
                                    )}
                                </div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Expires: {c.expires_on}
                                </div>
                                {c.review_notes && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Reviewer note: {c.review_notes}
                                    </p>
                                )}
                                {c.document_url && (
                                    <a
                                        href={c.document_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="mt-1 inline-block text-xs text-primary underline"
                                    >
                                        View document
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    onSubmit={submit}
                    className="mt-8 space-y-4 rounded-lg border border-border p-4"
                    data-tour-id="coi-portal-upload"
                >
                    <h2 className="font-semibold">Upload a certificate</h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="policy_type">Policy type</Label>
                            <select
                                id="policy_type"
                                className={selectClass}
                                value={data.policy_type}
                                onChange={(e) =>
                                    setData('policy_type', e.target.value)
                                }
                            >
                                {policy_types.map((t) => (
                                    <option key={t.value} value={t.value}>
                                        {t.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.policy_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="carrier">Carrier</Label>
                            <Input
                                id="carrier"
                                value={data.carrier}
                                onChange={(e) =>
                                    setData('carrier', e.target.value)
                                }
                            />
                            <InputError message={errors.carrier} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="policy_number">Policy number</Label>
                            <Input
                                id="policy_number"
                                value={data.policy_number}
                                onChange={(e) =>
                                    setData('policy_number', e.target.value)
                                }
                            />
                            <InputError message={errors.policy_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="expires_on">Expiry date</Label>
                            <Input
                                id="expires_on"
                                type="date"
                                value={data.expires_on}
                                onChange={(e) =>
                                    setData('expires_on', e.target.value)
                                }
                            />
                            <InputError message={errors.expires_on} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="document">
                            Certificate document (PDF or image)
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

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Upload certificate
                    </Button>
                </form>
            </div>
        </>
    );
}
