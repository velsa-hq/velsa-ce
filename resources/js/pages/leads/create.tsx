import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/leads';
import { index } from '@/routes/pipeline';

type Props = {
    clients: { id: number; name: string }[];
    venues: { id: number; name: string }[];
    stages: string[];
    stage_labels: Record<string, string>;
};

export default function LeadsCreate({
    clients,
    venues,
    stages,
    stage_labels,
}: Props) {
    const [stage, setStage] = useState(stages[0] ?? 'new');

    return (
        <>
            <Head title="New opportunity" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New opportunity
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Add a lead to the sales pipeline. Probability is set
                        automatically from the stage and can be tuned later.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
                    data-tour-id="lead-create-form"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        type="text"
                                        required
                                        autoFocus
                                        placeholder="e.g. Conservation Gala 2027"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="client_id">Client</Label>
                                    <select
                                        id="client_id"
                                        name="client_id"
                                        required
                                        defaultValue=""
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="" disabled>
                                            Select a client...
                                        </option>
                                        {clients.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.client_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="venue_id">Venue</Label>
                                    <select
                                        id="venue_id"
                                        name="venue_id"
                                        defaultValue=""
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">No venue yet</option>
                                        {venues.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {v.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.venue_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="stage">Stage</Label>
                                    <select
                                        id="stage"
                                        name="stage"
                                        required
                                        value={stage}
                                        onChange={(e) =>
                                            setStage(e.target.value)
                                        }
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    >
                                        {stages.map((s) => (
                                            <option key={s} value={s}>
                                                {stage_labels[s] ?? s}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.stage} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="estimated_value_dollars">
                                        Estimated value (USD)
                                    </Label>
                                    <Input
                                        id="estimated_value_dollars"
                                        name="estimated_value_dollars"
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        placeholder="0.00"
                                    />
                                    <InputError
                                        message={errors.estimated_value_dollars}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="expected_close_date">
                                        Expected close date
                                    </Label>
                                    <Input
                                        id="expected_close_date"
                                        name="expected_close_date"
                                        type="date"
                                    />
                                    <InputError
                                        message={errors.expected_close_date}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="source">Source</Label>
                                    <Input
                                        id="source"
                                        name="source"
                                        type="text"
                                        placeholder="referral, website, event, ..."
                                    />
                                    <InputError message={errors.source} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={4}
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Create opportunity
                                </Button>
                                <Link
                                    href={index().url}
                                    className="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

LeadsCreate.layout = {
    breadcrumbs: [
        { title: 'Pipeline', href: '/pipeline' },
        { title: 'New opportunity', href: '#' },
    ],
};
