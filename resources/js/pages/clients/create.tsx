import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/clients';

type Props = { types: string[] };

const TYPE_LABELS: Record<string, string> = {
    individual: 'Individual',
    business: 'Business',
    government: 'Government',
    nonprofit: 'Non-profit',
    educational: 'Educational',
};

type CustomField = { key: string; value: string };

export default function ClientsCreate({ types }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        type: string;
        industry: string;
        source: string;
        notes: string;
        tax_id: string;
        address: {
            street: string;
            city: string;
            state: string;
            postal_code: string;
        };
        custom_fields: CustomField[];
        contact: { name: string; role: string; email: string; phone: string };
    }>({
        name: '',
        type: types[0] ?? 'business',
        industry: '',
        source: '',
        notes: '',
        tax_id: '',
        address: { street: '', city: '', state: '', postal_code: '' },
        custom_fields: [],
        contact: { name: '', role: '', email: '', phone: '' },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url);
    };

    const addField = () =>
        setData('custom_fields', [
            ...data.custom_fields,
            { key: '', value: '' },
        ]);
    const setField = (i: number, patch: Partial<CustomField>) =>
        setData(
            'custom_fields',
            data.custom_fields.map((f, idx) =>
                idx === i ? { ...f, ...patch } : f,
            ),
        );
    const removeField = (i: number) =>
        setData(
            'custom_fields',
            data.custom_fields.filter((_, idx) => idx !== i),
        );

    return (
        <>
            <Head title="New client" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New client
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Create a client and, optionally, its primary contact.
                        You can add more contacts afterward from the client
                        page.
                    </p>
                </header>

                <form
                    onSubmit={submit}
                    className="flex max-w-3xl flex-col gap-6"
                    data-tour-id="client-create-form"
                >
                    <ClientFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        types={types}
                        addField={addField}
                        setField={setField}
                        removeField={removeField}
                    />

                    <fieldset className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
                        <legend className="px-1 text-sm font-medium">
                            Primary contact (optional)
                        </legend>
                        <div className="grid gap-2">
                            <Label htmlFor="c_name">Name</Label>
                            <Input
                                id="c_name"
                                value={data.contact.name}
                                onChange={(e) =>
                                    setData('contact', {
                                        ...data.contact,
                                        name: e.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c_role">Role / title</Label>
                            <Input
                                id="c_role"
                                value={data.contact.role}
                                onChange={(e) =>
                                    setData('contact', {
                                        ...data.contact,
                                        role: e.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c_email">Email</Label>
                            <Input
                                id="c_email"
                                type="email"
                                value={data.contact.email}
                                onChange={(e) =>
                                    setData('contact', {
                                        ...data.contact,
                                        email: e.target.value,
                                    })
                                }
                            />
                            <InputError message={errors['contact.email']} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="c_phone">Phone</Label>
                            <Input
                                id="c_phone"
                                value={data.contact.phone}
                                onChange={(e) =>
                                    setData('contact', {
                                        ...data.contact,
                                        phone: e.target.value,
                                    })
                                }
                            />
                        </div>
                    </fieldset>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Create client
                        </Button>
                        <Link
                            href={index().url}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </>
    );
}

// shared client-metadata fields, also used by the edit page
export function ClientFields({
    data,
    setData,
    errors,
    types,
    addField,
    setField,
    removeField,
}: {
    data: any;
    setData: (key: any, value: any) => void;
    errors: Record<string, string>;
    types: string[];
    addField: () => void;
    setField: (i: number, patch: Partial<CustomField>) => void;
    removeField: (i: number) => void;
}) {
    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        required
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="type">Type</Label>
                    <select
                        id="type"
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    >
                        {types.map((t) => (
                            <option key={t} value={t}>
                                {TYPE_LABELS[t] ?? t}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.type} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="industry">Industry / vertical</Label>
                    <Input
                        id="industry"
                        value={data.industry}
                        onChange={(e) => setData('industry', e.target.value)}
                        placeholder="Hospitality, Agriculture, Education..."
                    />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="source">Source</Label>
                    <Input
                        id="source"
                        value={data.source}
                        onChange={(e) => setData('source', e.target.value)}
                        placeholder="referral, website, event, partner, ..."
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="tax_id">Tax ID</Label>
                    <Input
                        id="tax_id"
                        value={data.tax_id}
                        onChange={(e) => setData('tax_id', e.target.value)}
                        placeholder="e.g. 12-3456789"
                    />
                </div>
            </div>

            <fieldset className="grid gap-4 rounded-lg border border-border p-4 sm:grid-cols-2">
                <legend className="px-1 text-sm font-medium">Address</legend>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="a_street">Street</Label>
                    <Input
                        id="a_street"
                        value={data.address.street}
                        onChange={(e) =>
                            setData('address', {
                                ...data.address,
                                street: e.target.value,
                            })
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="a_city">City</Label>
                    <Input
                        id="a_city"
                        value={data.address.city}
                        onChange={(e) =>
                            setData('address', {
                                ...data.address,
                                city: e.target.value,
                            })
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="a_state">State</Label>
                    <Input
                        id="a_state"
                        value={data.address.state}
                        onChange={(e) =>
                            setData('address', {
                                ...data.address,
                                state: e.target.value,
                            })
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="a_postal">Postal code</Label>
                    <Input
                        id="a_postal"
                        value={data.address.postal_code}
                        onChange={(e) =>
                            setData('address', {
                                ...data.address,
                                postal_code: e.target.value,
                            })
                        }
                    />
                </div>
            </fieldset>

            <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-4">
                <legend className="px-1 text-sm font-medium">
                    Custom fields
                </legend>
                {data.custom_fields.length === 0 ? (
                    <p className="text-xs text-muted-foreground">
                        No custom fields. Add ad-hoc key/value details below.
                    </p>
                ) : null}
                {data.custom_fields.map((f: CustomField, i: number) => (
                    <div key={i} className="flex items-center gap-2">
                        <Input
                            placeholder="Field"
                            value={f.key}
                            onChange={(e) =>
                                setField(i, { key: e.target.value })
                            }
                            className="max-w-[14rem]"
                        />
                        <Input
                            placeholder="Value"
                            value={f.value}
                            onChange={(e) =>
                                setField(i, { value: e.target.value })
                            }
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => removeField(i)}
                        >
                            Remove
                        </Button>
                    </div>
                ))}
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addField}
                    >
                        + Add field
                    </Button>
                </div>
            </fieldset>

            <div className="grid gap-2">
                <Label htmlFor="notes">Notes</Label>
                <textarea
                    id="notes"
                    rows={4}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="max-w-3xl rounded-md border border-border bg-background px-3 py-2 text-sm"
                />
            </div>
        </>
    );
}

ClientsCreate.layout = {
    breadcrumbs: [
        { title: 'Clients', href: '/clients' },
        { title: 'New', href: '#' },
    ],
};
