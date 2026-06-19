import { Head, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/admin/insurance-certificates';

type Option = { value: number; label: string };
type PolicyType = { value: string; label: string };

type Props = {
    policy_types: PolicyType[];
    clients: Option[];
    exhibitors: Option[];
};

const selectClass =
    'w-full rounded-md border border-border bg-background px-3 py-2 text-sm';
const inputWrap = 'grid gap-2';

export default function InsuranceCertificatesCreate({
    policy_types,
    clients,
    exhibitors,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        holder_kind: string;
        holder_id: string;
        policy_type: string;
        carrier: string;
        policy_number: string;
        coverage_amount: string;
        effective_date: string;
        expires_on: string;
        notes: string;
        document: File | null;
    }>({
        holder_kind: 'client',
        holder_id: '',
        policy_type: policy_types[0]?.value ?? 'general_liability',
        carrier: '',
        policy_number: '',
        coverage_amount: '',
        effective_date: '',
        expires_on: '',
        notes: '',
        document: null,
    });

    const holders = useMemo(
        () => (data.holder_kind === 'exhibitor' ? exhibitors : clients),
        [data.holder_kind, clients, exhibitors],
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, { forceFormData: true });
    };

    return (
        <>
            <Head title="New insurance certificate · Admin" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <h1 className="mb-6 text-2xl font-semibold tracking-tight">
                    New insurance certificate
                </h1>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className={inputWrap}>
                            <Label htmlFor="holder_kind">Holder type</Label>
                            <select
                                id="holder_kind"
                                className={selectClass}
                                value={data.holder_kind}
                                onChange={(e) => {
                                    setData('holder_kind', e.target.value);
                                    setData('holder_id', '');
                                }}
                            >
                                <option value="client">Client</option>
                                <option value="exhibitor">Exhibitor</option>
                            </select>
                            <InputError message={errors.holder_kind} />
                        </div>

                        <div className={inputWrap}>
                            <Label htmlFor="holder_id">
                                {data.holder_kind === 'exhibitor'
                                    ? 'Exhibitor'
                                    : 'Client'}
                            </Label>
                            <select
                                id="holder_id"
                                className={selectClass}
                                value={data.holder_id}
                                onChange={(e) =>
                                    setData('holder_id', e.target.value)
                                }
                            >
                                <option value="">Select...</option>
                                {holders.map((h) => (
                                    <option key={h.value} value={h.value}>
                                        {h.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.holder_id} />
                        </div>

                        <div className={inputWrap}>
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

                        <div className={inputWrap}>
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

                        <div className={inputWrap}>
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

                        <div className={inputWrap}>
                            <Label htmlFor="coverage_amount">
                                Coverage amount (USD)
                            </Label>
                            <Input
                                id="coverage_amount"
                                type="number"
                                min="0"
                                step="1"
                                value={data.coverage_amount}
                                onChange={(e) =>
                                    setData('coverage_amount', e.target.value)
                                }
                            />
                            <InputError message={errors.coverage_amount} />
                        </div>

                        <div className={inputWrap}>
                            <Label htmlFor="effective_date">
                                Effective date
                            </Label>
                            <Input
                                id="effective_date"
                                type="date"
                                value={data.effective_date}
                                onChange={(e) =>
                                    setData('effective_date', e.target.value)
                                }
                            />
                            <InputError message={errors.effective_date} />
                        </div>

                        <div className={inputWrap}>
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

                    <div className={inputWrap}>
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

                    <div className={inputWrap}>
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            rows={3}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner className="mr-2" />}
                        Record certificate
                    </Button>
                </form>
            </div>
        </>
    );
}

InsuranceCertificatesCreate.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/insurance-certificates' },
        {
            title: 'Insurance certificates',
            href: '/admin/insurance-certificates',
        },
        { title: 'New', href: '#' },
    ],
};
