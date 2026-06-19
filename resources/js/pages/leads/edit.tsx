import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { show, update } from '@/routes/leads';

type Lead = {
    id: number;
    name: string;
    stage: string;
    client_id: number;
    venue_id: number | null;
    estimated_value_dollars: string | null;
    probability: number;
    expected_close_date: string | null;
    source: string | null;
    lost_reason: string | null;
    notes: string | null;
};

type Props = {
    lead: Lead;
    clients: { id: number; name: string }[];
    venues: { id: number; name: string }[];
    stages: string[];
    stage_labels: Record<string, string>;
};

export default function LeadsEdit({
    lead,
    clients,
    venues,
    stages,
    stage_labels,
}: Props) {
    const [stage, setStage] = useState(lead.stage);

    return (
        <>
            <Head title={`Edit ${lead.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Edit lead
                    </h1>
                    <p className="text-sm text-muted-foreground">{lead.name}</p>
                </header>

                <Form
                    {...update.form(lead.id)}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
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
                                        defaultValue={lead.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="client_id">Client</Label>
                                    <select
                                        id="client_id"
                                        name="client_id"
                                        required
                                        defaultValue={lead.client_id}
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    >
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
                                        defaultValue={lead.venue_id ?? ''}
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
                                    <Label htmlFor="probability">
                                        Probability (0-1)
                                    </Label>
                                    <Input
                                        id="probability"
                                        name="probability"
                                        type="number"
                                        step={0.01}
                                        min={0}
                                        max={1}
                                        required
                                        defaultValue={lead.probability}
                                    />
                                    <InputError message={errors.probability} />
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
                                        defaultValue={
                                            lead.estimated_value_dollars ?? ''
                                        }
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
                                        defaultValue={
                                            lead.expected_close_date ?? ''
                                        }
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
                                        defaultValue={lead.source ?? ''}
                                        placeholder="referral, website, event, ..."
                                    />
                                    <InputError message={errors.source} />
                                </div>

                                {stage === 'lost' ? (
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="lost_reason">
                                            Lost reason
                                        </Label>
                                        <Input
                                            id="lost_reason"
                                            name="lost_reason"
                                            type="text"
                                            defaultValue={
                                                lead.lost_reason ?? ''
                                            }
                                            placeholder="budget / timing / competition / fit"
                                        />
                                        <InputError
                                            message={errors.lost_reason}
                                        />
                                    </div>
                                ) : null}

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <textarea
                                        id="notes"
                                        name="notes"
                                        rows={4}
                                        defaultValue={lead.notes ?? ''}
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.notes} />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save changes
                                </Button>
                                <Link
                                    href={show(lead.id).url}
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

LeadsEdit.layout = {
    breadcrumbs: [
        { title: 'Pipeline', href: '/pipeline' },
        { title: 'Edit', href: '#' },
    ],
};
