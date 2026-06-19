import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ClientFields } from '@/pages/clients/create';
import { show, update } from '@/routes/clients';

type CustomField = { key: string; value: string };

type Client = {
    id: number;
    name: string;
    type: string | null;
    industry: string | null;
    source: string | null;
    notes: string | null;
    tax_id: string | null;
    address: {
        street?: string;
        city?: string;
        state?: string;
        postal_code?: string;
    };
    custom_fields: CustomField[];
};

type Props = { client: Client; types: string[] };

export default function ClientsEdit({ client, types }: Props) {
    const { data, setData, put, processing, errors } = useForm<{
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
    }>({
        name: client.name,
        type: client.type ?? 'business',
        industry: client.industry ?? '',
        source: client.source ?? '',
        notes: client.notes ?? '',
        tax_id: client.tax_id ?? '',
        address: {
            street: client.address?.street ?? '',
            city: client.address?.city ?? '',
            state: client.address?.state ?? '',
            postal_code: client.address?.postal_code ?? '',
        },
        custom_fields: client.custom_fields ?? [],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(update(client.id).url, { preserveScroll: true });
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
            <Head title={`Edit ${client.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit client
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {client.name}
                    </p>
                </header>

                <form
                    onSubmit={submit}
                    className="flex max-w-3xl flex-col gap-6"
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

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save changes
                        </Button>
                        <Link
                            href={show(client.id).url}
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>

                <p className="max-w-3xl text-xs text-muted-foreground">
                    Manage contacts (add, edit, set primary, remove) from the
                    client detail page.
                </p>
            </div>
        </>
    );
}

ClientsEdit.layout = {
    breadcrumbs: [
        { title: 'Clients', href: '/clients' },
        { title: 'Edit', href: '#' },
    ],
};
