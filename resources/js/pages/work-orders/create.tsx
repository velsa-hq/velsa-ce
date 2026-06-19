import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/work-orders';

type VenueOption = { id: number; name: string; slug: string };
type KindOption = { value: string; label: string };
type Assignee = { id: number; name: string; email: string };

type Props = {
    venues: VenueOption[];
    selected_venue_id: number | null;
    kinds: KindOption[];
    assignees: Assignee[];
};

const PRIORITIES: { value: number; label: string }[] = [
    { value: 1, label: 'P1 · Critical' },
    { value: 2, label: 'P2 · High' },
    { value: 3, label: 'P3 · Normal' },
    { value: 4, label: 'P4 · Low' },
    { value: 5, label: 'P5 · Backlog' },
];

const selectClass =
    'rounded-md border border-border bg-background px-3 py-2 text-sm';

export default function WorkOrderCreate({
    venues,
    selected_venue_id,
    kinds,
    assignees,
}: Props) {
    return (
        <>
            <Head title="New work order" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        New work order
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Schedule maintenance, setup, repairs, or support against
                        a venue.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    className="flex max-w-3xl flex-col gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        type="text"
                                        required
                                        placeholder="e.g. Replace HVAC filter in Hall A"
                                        data-tour-id="wo-title"
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="venue_id">Venue</Label>
                                    <select
                                        id="venue_id"
                                        name="venue_id"
                                        required
                                        defaultValue={
                                            selected_venue_id
                                                ? String(selected_venue_id)
                                                : ''
                                        }
                                        className={selectClass}
                                        data-tour-id="wo-venue"
                                    >
                                        <option value="" disabled>
                                            Select a venue...
                                        </option>
                                        {venues.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {v.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.venue_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="kind">Kind</Label>
                                    <select
                                        id="kind"
                                        name="kind"
                                        required
                                        defaultValue=""
                                        className={selectClass}
                                        data-tour-id="wo-kind"
                                    >
                                        <option value="" disabled>
                                            Select a kind...
                                        </option>
                                        {kinds.map((k) => (
                                            <option
                                                key={k.value}
                                                value={k.value}
                                            >
                                                {k.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.kind} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority">Priority</Label>
                                    <select
                                        id="priority"
                                        name="priority"
                                        defaultValue="3"
                                        className={selectClass}
                                    >
                                        {PRIORITIES.map((p) => (
                                            <option
                                                key={p.value}
                                                value={p.value}
                                            >
                                                {p.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.priority} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="scheduled_for">
                                        Scheduled for (optional)
                                    </Label>
                                    <Input
                                        id="scheduled_for"
                                        name="scheduled_for"
                                        type="datetime-local"
                                    />
                                    <InputError
                                        message={errors.scheduled_for}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="assigned_to_user_id">
                                        Assignee (optional)
                                    </Label>
                                    <select
                                        id="assigned_to_user_id"
                                        name="assigned_to_user_id"
                                        defaultValue=""
                                        className={selectClass}
                                    >
                                        <option value="">- Unassigned -</option>
                                        {assignees.map((a) => (
                                            <option key={a.id} value={a.id}>
                                                {a.name} ({a.email})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.assigned_to_user_id}
                                    />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="description">
                                        Description (optional)
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={4}
                                        placeholder="Details, scope, access notes..."
                                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors.description} />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-tour-id="wo-submit"
                                >
                                    {processing && <Spinner />}
                                    Create work order
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

WorkOrderCreate.layout = {
    breadcrumbs: [
        { title: 'Work orders', href: '/work-orders' },
        { title: 'New', href: '#' },
    ],
};
